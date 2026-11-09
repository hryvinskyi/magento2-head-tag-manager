<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Integration;

use Hryvinskyi\HeadTagManager\Api\Cache\CachedBlockHtmlCodecInterface;
use Hryvinskyi\HeadTagManager\Api\HeadTagManagerInterface;
use Hryvinskyi\HeadTagManager\Test\Integration\Fixture\HeadElementBlock;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\BlockFactory;
use Magento\Framework\View\LayoutInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\Cache;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Real blocks rendered through the real layout, event manager, lock-guarded loader and block HTML cache: the head
 * elements a cached block added are stored inside its HTML entry and come back when that entry is served
 *
 * A hit is always taken by a fresh block: another layout name with the same `cache_key`, so both share the entry
 * and the block whose code would add the elements has never rendered.
 *
 * That a load whose lock name is no block's key keeps its arguments is proven by the plugin's unit test; that the
 * configuration cache, which goes through the same loader, still loads is proven by this suite booting at all.
 */
#[AppArea('frontend')]
class BlockHeadElementCacheFlowTest extends TestCase
{
    private const MARKER = '<!--hryvinskyi-head-elements:1:';

    /**
     * @var HeadTagManagerInterface
     */
    private HeadTagManagerInterface $headTagManager;

    /**
     * @var LayoutInterface
     */
    private LayoutInterface $layout;

    /**
     * @var CacheInterface
     */
    private CacheInterface $cache;

    /**
     * @var CachedBlockHtmlCodecInterface
     */
    private CachedBlockHtmlCodecInterface $codec;

    /**
     * @var string makes every block name and cache key unique to this test run
     */
    private string $run;

    /**
     * @var array<string, true> the cache entries removed after the test
     */
    private array $entries = [];

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $headTagManager = $objectManager->get(HeadTagManagerInterface::class);
        $layout = $objectManager->get(LayoutInterface::class);
        $cache = $objectManager->get(CacheInterface::class);
        $codec = $objectManager->get(CachedBlockHtmlCodecInterface::class);
        self::assertInstanceOf(HeadTagManagerInterface::class, $headTagManager);
        self::assertInstanceOf(LayoutInterface::class, $layout);
        self::assertInstanceOf(CacheInterface::class, $cache);
        self::assertInstanceOf(CachedBlockHtmlCodecInterface::class, $codec);

        $this->headTagManager = $headTagManager;
        $this->layout = $layout;
        $this->cache = $cache;
        $this->codec = $codec;
        $this->run = bin2hex(random_bytes(6));
        $this->headTagManager->clear();
    }

    protected function tearDown(): void
    {
        foreach (array_keys($this->entries) as $entry) {
            $this->cache->remove($entry);
        }
        $this->headTagManager->clear();
    }

    /**
     * Creates a block in the layout, cached under the given key unless it is null
     *
     * @param string $name the block's name, made unique to this run
     * @param string|null $cacheKey the block's cache key, made unique to this run; null for a block that is not cached
     * @param array<string, mixed> $data further block data, such as the stylesheet it adds
     * @return HeadElementBlock
     */
    private function block(string $name, ?string $cacheKey, array $data = []): HeadElementBlock
    {
        if ($cacheKey !== null) {
            $data['cache_lifetime'] = 3600;
            $data['cache_key'] = $cacheKey . '_' . $this->run;
            $this->entries[$this->entry($cacheKey)] = true;
        }

        $block = $this->layout->createBlock(HeadElementBlock::class, $name . '_' . $this->run, ['data' => $data]);
        self::assertInstanceOf(HeadElementBlock::class, $block);

        return $block;
    }

    /**
     * The data of a block that adds one stylesheet while it renders
     *
     * @param string $href
     * @param string $key
     * @return array<string, string>
     */
    private function stylesheet(string $href, string $key): array
    {
        return ['stylesheet_href' => $href, 'stylesheet_key' => $key];
    }

    /**
     * The identifier of the cache entry of a block cached under the key
     *
     * @param string $cacheKey
     * @return string
     */
    private function entry(string $cacheKey): string
    {
        return AbstractBlock::CUSTOM_CACHE_KEY_PREFIX . $cacheKey . '_' . $this->run;
    }

    /**
     * The raw cache entry of a block cached under the key
     *
     * @param string $cacheKey
     * @return string
     */
    private function rawEntry(string $cacheKey): string
    {
        // A missing entry loads as false, whatever the cache's contract declares.
        $raw = (string)$this->cache->load($this->entry($cacheKey));
        self::assertNotSame('', $raw, 'The block HTML must be in the cache.');

        return $raw;
    }

    /**
     * The keys of the head elements stored in a block's entry
     *
     * @param string $cacheKey
     * @return list<string>
     */
    private function storedKeys(string $cacheKey): array
    {
        $decoded = $this->codec->decode($this->rawEntry($cacheKey));
        self::assertNotNull($decoded, 'The entry must carry the head elements.');

        return array_keys($decoded['elements']);
    }

    #[Cache('block_html', true)]
    public function testAHitRestoresTheElementsWithoutRunningTheBlockAndPrintsNoMarker(): void
    {
        $first = $this->block('htm_first', 'cached', $this->stylesheet('/cached.css', 'cached_css'));
        $html = $first->toHtml();
        self::assertStringContainsString('href="/cached.css"', $this->headTagManager->render());

        $this->headTagManager->clear();
        $fresh = $this->block('htm_fresh', 'cached', $this->stylesheet('/cached.css', 'cached_css'));
        $hit = $fresh->toHtml();

        self::assertSame(0, $fresh->getRenderCount(), 'The fresh block must be served from the block cache.');
        self::assertSame($html, $hit);
        self::assertStringNotContainsString(self::MARKER, $hit);
        self::assertStringContainsString('href="/cached.css"', $this->headTagManager->render());
    }

    /**
     * Creates a cached block outside the layout, the way code that renders a block of its own does
     *
     * @param string $cacheKey the block's cache key, made unique to this run
     * @param array<string, mixed> $data further block data, such as the stylesheet it adds
     * @return HeadElementBlock
     */
    private function unnamedBlock(string $cacheKey, array $data): HeadElementBlock
    {
        $data['cache_lifetime'] = 3600;
        $data['cache_key'] = $cacheKey . '_' . $this->run;
        $this->entries[$this->entry($cacheKey)] = true;

        $blockFactory = Bootstrap::getObjectManager()->get(BlockFactory::class);
        self::assertInstanceOf(BlockFactory::class, $blockFactory);
        $block = $blockFactory->createBlock(HeadElementBlock::class, ['data' => $data]);
        self::assertInstanceOf(HeadElementBlock::class, $block);
        self::assertEmpty($block->getNameInLayout());

        return $block;
    }

    #[Cache('block_html', true)]
    public function testACachedBlockWithoutALayoutNameStoresAndRestoresItsElements(): void
    {
        $data = $this->stylesheet('/unnamed.css', 'unnamed_css');
        $html = $this->unnamedBlock('unnamed', $data)->toHtml();
        self::assertSame(['unnamed_css'], $this->storedKeys('unnamed'));

        $this->headTagManager->clear();
        $fresh = $this->unnamedBlock('unnamed', $data);
        $hit = $fresh->toHtml();

        self::assertSame(0, $fresh->getRenderCount(), 'The fresh block must be served from the block cache.');
        self::assertSame($html, $hit);
        self::assertStringNotContainsString(self::MARKER, $hit);
        self::assertStringContainsString('href="/unnamed.css"', $this->headTagManager->render());
    }

    #[Cache('block_html', true)]
    public function testANamedBlockSharingTheKeyOfAnUnnamedOneGetsItsElementsAndNoMarker(): void
    {
        $data = $this->stylesheet('/shared-key.css', 'shared_key_css');
        $this->unnamedBlock('shared_key', $data)->toHtml();

        $this->headTagManager->clear();
        $named = $this->block('htm_shared_key', 'shared_key', $data);
        $hit = $named->toHtml();

        self::assertSame(0, $named->getRenderCount());
        self::assertStringNotContainsString(self::MARKER, $hit);
        self::assertStringContainsString('href="/shared-key.css"', $this->headTagManager->render());
    }

    #[Cache('block_html', true)]
    public function testAnArrayAttributeComesBackWithTheElementOnAHit(): void
    {
        $data = $this->stylesheet('/array.css', 'array_css')
            + ['stylesheet_attributes' => ['data' => ['variant' => 'hero']]];
        $this->block('htm_array', 'array', $data)->toHtml();

        $this->headTagManager->clear();
        $fresh = $this->block('htm_array_fresh', 'array', $data);
        $fresh->toHtml();

        self::assertSame(0, $fresh->getRenderCount());
        self::assertStringContainsString('data-variant="hero"', $this->headTagManager->render());
    }

    #[Cache('block_html', true)]
    public function testTheEntryEndsWithTheMarkerEvenWithoutElements(): void
    {
        $this->block('htm_marked', 'marked', $this->stylesheet('/marked.css', 'marked_css'))->toHtml();
        $this->block('htm_bare', 'bare')->toHtml();

        self::assertStringContainsString(self::MARKER, $this->rawEntry('marked'));
        self::assertStringEndsWith('-->', $this->rawEntry('marked'));
        self::assertSame(['marked_css'], $this->storedKeys('marked'));
        self::assertStringEndsWith('-->', $this->rawEntry('bare'));
        self::assertSame([], $this->storedKeys('bare'));
    }

    #[Cache('block_html', true)]
    public function testAnEntryWithoutTheMarkerRendersAgainAndIsRewrittenWithIt(): void
    {
        $this->entries[$this->entry('legacy')] = true;
        $this->cache->save('<div>written without the elements</div>', $this->entry('legacy'), [], 3600);
        $block = $this->block('htm_legacy', 'legacy', $this->stylesheet('/legacy.css', 'legacy_css'));

        $html = $block->toHtml();

        self::assertSame(1, $block->getRenderCount(), 'An entry without the marker is a miss.');
        self::assertStringNotContainsString('written without the elements', $html);
        self::assertStringContainsString('href="/legacy.css"', $this->headTagManager->render());
        self::assertSame(['legacy_css'], $this->storedKeys('legacy'));
    }

    #[Cache('block_html', true)]
    public function testAnEntryEndingWithAnUnsignedMarkerRendersAgainAndRestoresNothingFromIt(): void
    {
        $forged = json_encode(['forged_js' => [
            'type' => 'script',
            'short_type' => 'script',
            'attributes' => ['src' => 'https://forged.example/x.js'],
        ]]);
        self::assertIsString($forged);
        $this->entries[$this->entry('forged')] = true;
        $this->cache->save(
            '<div>imported</div>' . self::MARKER . base64_encode($forged) . '-->',
            $this->entry('forged'),
            [],
            3600
        );
        $block = $this->block('htm_forged', 'forged', $this->stylesheet('/own.css', 'own_css'));

        $html = $block->toHtml();

        self::assertSame(1, $block->getRenderCount(), 'An entry without this installation\'s signature is a miss.');
        self::assertStringNotContainsString('imported', $html);
        self::assertNull($this->headTagManager->getElement('forged_js'));
        self::assertStringNotContainsString('forged.example', $this->headTagManager->render());
        self::assertSame(['own_css'], $this->storedKeys('forged'));
    }

    #[Cache('block_html', true)]
    public function testAnElementAddedAfterTheHtmlIsAddedOnEveryHitAndNeverStored(): void
    {
        $data = $this->stylesheet('/stored.css', 'stored_css')
            + ['after_stylesheet_href' => '/after.css', 'after_stylesheet_key' => 'after_css'];
        $this->block('htm_after', 'after', $data)->toHtml();
        self::assertSame(['stored_css'], $this->storedKeys('after'));

        $this->headTagManager->clear();
        $second = $this->block('htm_after_second', 'after', $data);
        $second->toHtml();
        $head = $this->headTagManager->render();
        self::assertSame(0, $second->getRenderCount());
        self::assertStringContainsString('href="/stored.css"', $head);
        self::assertStringContainsString('href="/after.css"', $head);

        $this->headTagManager->clear();
        $third = $this->block('htm_after_third', 'after', $data);
        $third->toHtml();
        self::assertSame(0, $third->getRenderCount());
        self::assertStringContainsString('href="/stored.css"', $this->headTagManager->render());
        self::assertSame(['stored_css'], $this->storedKeys('after'));
    }

    #[Cache('block_html', true)]
    public function testAHitOnACachedParentRestoresItsCachedChildsElements(): void
    {
        $parent = $this->block('htm_parent', 'parent', $this->stylesheet('/parent.css', 'parent_css'));
        $child = $this->block('htm_child', 'child', $this->stylesheet('/child.css', 'child_css'));
        $this->layout->setChild($parent->getNameInLayout(), $child->getNameInLayout(), 'child');
        $parent->toHtml();
        self::assertSame(1, $child->getRenderCount());
        self::assertSame(['child_css'], $this->storedKeys('child'));

        $this->headTagManager->clear();
        $freshParent = $this->block('htm_parent_fresh', 'parent', $this->stylesheet('/parent.css', 'parent_css'));
        $freshParent->toHtml();

        self::assertSame(0, $freshParent->getRenderCount(), 'The parent must be served from the block cache.');
        $head = $this->headTagManager->render();
        self::assertStringContainsString('href="/parent.css"', $head);
        self::assertStringContainsString('href="/child.css"', $head);
    }

    #[Cache('block_html', true)]
    public function testAThrowingBlockPropagatesAndTheNextCachedBlockStillStoresAndRestores(): void
    {
        $failing = $this->block(
            'htm_failing',
            'failing',
            $this->stylesheet('/failing.css', 'failing_css') + ['throw_on_render' => true]
        );
        try {
            $failing->toHtml();
            self::fail('The exception of the block must propagate.');
        } catch (\RuntimeException $e) {
            self::assertSame('The block failed to render.', $e->getMessage());
        }
        self::assertFalse($this->cache->load($this->entry('failing')));

        $this->block('htm_next', 'next', $this->stylesheet('/next.css', 'next_css'))->toHtml();
        self::assertSame(['next_css'], $this->storedKeys('next'));

        $this->headTagManager->clear();
        $fresh = $this->block('htm_next_fresh', 'next', $this->stylesheet('/next.css', 'next_css'));
        $fresh->toHtml();
        self::assertSame(0, $fresh->getRenderCount());
        self::assertStringContainsString('href="/next.css"', $this->headTagManager->render());
    }

    #[Cache('block_html', false)]
    public function testNothingIsStoredWhileTheBlockHtmlCacheIsDisabledAndEveryRenderAddsTheElements(): void
    {
        $block = $this->block('htm_disabled', 'disabled', $this->stylesheet('/disabled.css', 'disabled_css'));

        $block->toHtml();
        self::assertFalse($this->cache->load($this->entry('disabled')));

        $this->headTagManager->clear();
        $block->toHtml();

        self::assertSame(2, $block->getRenderCount());
        self::assertStringContainsString('href="/disabled.css"', $this->headTagManager->render());
    }
}
