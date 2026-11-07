<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Integration;

use Hryvinskyi\Base\Api\Html\AttributeRendererInterface;
use Hryvinskyi\HeadTagManager\Api\Block\BlockCacheDetectorInterface;
use Hryvinskyi\HeadTagManager\Api\Block\HeadElementTrackerInterface;
use Hryvinskyi\HeadTagManager\Api\Cache\BlockHeadElementCacheInterface;
use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Api\HeadTagManagerInterface;
use Hryvinskyi\HeadTagManager\Api\Registry\HeadElementFactoryRegistryInterface;
use Hryvinskyi\HeadTagManager\Api\Serializer\HeadElementSerializerInterface;
use Hryvinskyi\HeadTagManager\Api\Serializer\Strategy\SerializationStrategyRegistryInterface;
use Hryvinskyi\HeadTagManager\Model\HeadElement\AbstractHeadElement;
use Hryvinskyi\HeadTagManager\Model\HeadElement\Factory\MetaElementFactory;
use Hryvinskyi\HeadTagManager\Model\HeadElement\Factory\ScriptElementFactory;
use Hryvinskyi\HeadTagManager\Model\HeadElement\LinkElement;
use Hryvinskyi\HeadTagManager\Model\HeadElement\MetaElement;
use Hryvinskyi\HeadTagManager\Model\HeadElement\ScriptElement;
use Hryvinskyi\HeadTagManager\Model\HeadElement\StyleElement;
use Hryvinskyi\HeadTagManager\Model\Serializer\Strategy\MetaElementSerializationStrategy;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The factory registry, the serializer and the head tag manager resolved through the real container, the block-level
 * caching flow included
 *
 * Elements come from the registry, so the pool's wiring and the generated element factories are exercised with them.
 */
class HeadTagManagerIntegrationTest extends TestCase
{
    /**
     * @var HeadElementFactoryRegistryInterface
     */
    private HeadElementFactoryRegistryInterface $factoryRegistry;

    /**
     * @var SerializationStrategyRegistryInterface
     */
    private SerializationStrategyRegistryInterface $strategyRegistry;

    /**
     * @var HeadElementSerializerInterface
     */
    private HeadElementSerializerInterface $serializer;

    protected function setUp(): void
    {
        $this->factoryRegistry = $this->service(HeadElementFactoryRegistryInterface::class);
        $this->strategyRegistry = $this->service(SerializationStrategyRegistryInterface::class);
        $this->serializer = $this->service(HeadElementSerializerInterface::class);
    }

    /**
     * Resolves a service through the real container.
     *
     * @template T of object
     * @param class-string<T> $type the interface or class to resolve
     * @return T
     */
    private function service(string $type): object
    {
        $service = Bootstrap::getObjectManager()->get($type);
        self::assertInstanceOf($type, $service);

        return $service;
    }

    /**
     * Creates an element through the factory the registry holds for its type.
     *
     * @param string $type the element type
     * @param array<string, mixed> $data the data the factory takes
     * @return HeadElementInterface
     */
    private function element(string $type, array $data = []): HeadElementInterface
    {
        $factory = $this->factoryRegistry->getFactoryByType($type);
        self::assertNotNull($factory);

        return $factory->create($data);
    }

    /**
     * Creates a head tag manager that holds no elements.
     *
     * @return HeadTagManagerInterface
     */
    private function emptyManager(): HeadTagManagerInterface
    {
        $manager = Bootstrap::getObjectManager()->create(HeadTagManagerInterface::class);
        self::assertInstanceOf(HeadTagManagerInterface::class, $manager);

        return $manager;
    }

    /**
     * Meta and link tags keep the exact text the static attribute rendering of the previous major version produced
     */
    public function testMetaAndLinkRenderByteForByteAsThePreviousMajorVersion(): void
    {
        $manager = $this->emptyManager();
        $manager->addMetaName('description', 'Tom & "Jerry" <b>\'s</b>', 'description');
        $manager->addMeta(['content' => 'x', 'property' => 'og:title'], 'og');
        $manager->addCharset();
        $manager->addLink(['rel' => 'stylesheet', 'href' => '/a.css?x=1&y=2'], 'css');
        $manager->addLink(
            ['href' => '/f.woff2', 'as' => 'font', 'rel' => 'preload', 'type' => 'font/woff2', 'crossorigin' => true],
            'font'
        );
        $manager->addLink(
            ['media' => 'print', 'title' => 't', 'rel' => 'alternate', 'href' => 'https://e.com/?a=1&amp;b=2'],
            'alternate'
        );

        /** @noinspection HtmlUnknownTarget */
        $expected = [
            '<meta name="description" content="Tom &amp; &quot;Jerry&quot; &lt;b&gt;&#039;s&lt;/b&gt;">',
            '<meta content="x" property="og:title">',
            '<meta charset="UTF-8">',
            '<link href="/a.css?x=1&amp;y=2" rel="stylesheet">',
            '<link type="font/woff2" href="/f.woff2" rel="preload" as="font" crossorigin>',
            '<link href="https://e.com/?a=1&amp;amp;b=2" title="t" rel="alternate" media="print">',
        ];

        self::assertSame(implode(PHP_EOL, $expected) . PHP_EOL, $manager->render());
    }

    /**
     * Each type's factory yields its element, with the serializer's `sort_order` key reaching the element
     */
    public function testRegistryFactoriesCreateElementsThroughTheContainer(): void
    {
        $meta = $this->element('meta', ['attributes' => ['name' => 'a'], 'sort_order' => 3]);
        $link = $this->element('link', ['attributes' => ['rel' => 'b'], 'sort_order' => 4]);
        $script = $this->element('script', ['attributes' => ['id' => 'c'], 'content' => 'x();', 'sort_order' => 5]);
        $style = $this->element('style', ['attributes' => ['id' => 'd'], 'content' => 'a{}', 'sort_order' => 6]);

        self::assertInstanceOf(MetaElement::class, $meta);
        self::assertInstanceOf(LinkElement::class, $link);
        self::assertInstanceOf(ScriptElement::class, $script);
        self::assertInstanceOf(StyleElement::class, $style);
        self::assertSame(['name' => 'a'], $meta->getAttributes());
        self::assertSame(['rel' => 'b'], $link->getAttributes());
        self::assertSame(['id' => 'c'], $script->getAttributes());
        self::assertSame(['id' => 'd'], $style->getAttributes());
        self::assertSame('x();', $script->getContent());
        self::assertSame('a{}', $style->getContent());
        self::assertSame(3, $meta->getSortOrder());
        self::assertSame(4, $link->getSortOrder());
        self::assertSame(5, $script->getSortOrder());
        self::assertSame(6, $style->getSortOrder());
    }

    /**
     * Without a sort order a style renders ahead of every other element type, and no element shares an instance
     */
    public function testDefaultSortOrdersAndFreshInstances(): void
    {
        self::assertSame(100, $this->element('meta')->getSortOrder());
        self::assertSame(100, $this->element('link')->getSortOrder());
        self::assertSame(100, $this->element('script')->getSortOrder());
        self::assertSame(10, $this->element('style')->getSortOrder());
        self::assertNotSame($this->element('meta'), $this->element('meta'));
    }

    /**
     * Script and style tags are rendered by the secure renderer the container injects
     */
    public function testScriptAndStyleRenderThroughTheSecureRenderer(): void
    {
        $manager = $this->emptyManager();
        $manager->addExternalScript('https://example.com/a.js', [], 'script');
        $manager->addInlineStyle('a{}', ['media' => 'print'], 'style');

        self::assertSame(
            [
                'script' => '<script src="https://example.com/a.js"></script>',
                'style' => '<style media="print">a{}</style>',
            ],
            $manager->getRenderedElements()
        );
    }

    public function testCompleteSerializationAndDeserializationFlow(): void
    {
        // Create original elements
        $elements = [
            'meta_description' => $this->element('meta', [
                'attributes' => ['name' => 'description', 'content' => 'Test'],
            ]),
            'inline_script' => $this->element('script', [
                'attributes' => ['type' => 'text/javascript'],
                'content' => 'console.log("test");',
            ]),
        ];

        // Serialize elements
        $serializedData = $this->serializer->serialize($elements);

        // Verify serialized data structure
        self::assertSame(
            [
                'meta_description' => [
                    'type' => MetaElement::class,
                    'short_type' => 'meta',
                    'attributes' => ['name' => 'description', 'content' => 'Test'],
                    'sort_order' => 100,
                ],
                'inline_script' => [
                    'type' => ScriptElement::class,
                    'short_type' => 'script',
                    'attributes' => ['type' => 'text/javascript'],
                    'sort_order' => 100,
                    'content' => 'console.log("test");',
                ],
            ],
            $serializedData
        );

        // Deserialize elements
        $deserializedElements = $this->serializer->unserialize($serializedData);

        // Verify deserialized elements
        self::assertCount(2, $deserializedElements);
        self::assertArrayHasKey('meta_description', $deserializedElements);
        self::assertArrayHasKey('inline_script', $deserializedElements);

        $deserializedMeta = $deserializedElements['meta_description'];
        $deserializedScript = $deserializedElements['inline_script'];

        // Verify meta element reconstruction
        self::assertInstanceOf(MetaElement::class, $deserializedMeta);
        self::assertEquals(['name' => 'description', 'content' => 'Test'], $deserializedMeta->getAttributes());
        self::assertEquals(100, $deserializedMeta->getSortOrder());

        // Verify script element reconstruction
        self::assertInstanceOf(ScriptElement::class, $deserializedScript);
        self::assertEquals(['type' => 'text/javascript'], $deserializedScript->getAttributes());
        self::assertEquals('console.log("test");', $deserializedScript->getContent());
        self::assertEquals(100, $deserializedScript->getSortOrder());
    }

    public function testStrategyRegistryIntegration(): void
    {
        $metaElement = $this->element('meta', ['attributes' => ['name' => 'keywords', 'content' => 'test']]);

        // Verify strategy can be found for element
        $strategy = $this->strategyRegistry->getStrategyForElement($metaElement);
        self::assertInstanceOf(MetaElementSerializationStrategy::class, $strategy);

        // Verify strategy can serialize element
        $serializedData = $strategy->serialize($metaElement, 'test-key');

        $expected = [
            'type' => MetaElement::class,
            'short_type' => 'meta',
            'attributes' => ['name' => 'keywords', 'content' => 'test'],
            'sort_order' => 100
        ];

        self::assertEquals($expected, $serializedData);
    }

    public function testFactoryRegistryIntegration(): void
    {
        // Test factory lookup by type
        $metaFactory = $this->factoryRegistry->getFactoryByType('meta');
        self::assertInstanceOf(MetaElementFactory::class, $metaFactory);

        // Test factory lookup by class name
        $scriptFactory = $this->factoryRegistry->getFactoryByClassName(ScriptElement::class);
        self::assertInstanceOf(ScriptElementFactory::class, $scriptFactory);

        // Test element type lookup
        $elementType = $this->factoryRegistry->getElementTypeByClassName(MetaElement::class);
        self::assertEquals('meta', $elementType);
    }

    public function testBackwardCompatibilityWithShortTypes(): void
    {
        // Test deserializing data with only short_type (backward compatibility)
        $legacyData = [
            'meta_key' => [
                'short_type' => 'meta',
                'attributes' => ['name' => 'author', 'content' => 'Test Author']
            ],
            'script_key' => [
                'short_type' => 'script',
                'attributes' => ['defer' => 'defer'],
                'content' => 'alert("legacy");'
            ]
        ];

        $deserializedElements = $this->serializer->unserialize($legacyData);

        self::assertCount(2, $deserializedElements);

        $metaElement = $deserializedElements['meta_key'];
        $scriptElement = $deserializedElements['script_key'];

        self::assertInstanceOf(MetaElement::class, $metaElement);
        self::assertInstanceOf(ScriptElement::class, $scriptElement);

        self::assertEquals(['name' => 'author', 'content' => 'Test Author'], $metaElement->getAttributes());
        self::assertEquals(['defer' => 'defer'], $scriptElement->getAttributes());
        self::assertEquals('alert("legacy");', $scriptElement->getContent());
    }

    public function testSerializationWithoutStrategy(): void
    {
        // Create an unknown element class for testing
        $unknownElement = new class (
            $this->service(SecureHtmlRenderer::class),
            $this->service(AttributeRendererInterface::class),
            ['custom' => 'attribute']
        ) extends AbstractHeadElement {
            /**
             * @inheritDoc
             */
            public function render(): string
            {
                return '<unknown' . $this->attributesToString() . '>';
            }
        };

        $elements = ['unknown' => $unknownElement];
        $serializedData = $this->serializer->serialize($elements);

        // Should fall back to basic serialization
        self::assertSame(
            [
                'unknown' => [
                    'type' => get_class($unknownElement),
                    'short_type' => 'unknown',
                    'attributes' => ['custom' => 'attribute'],
                    'sort_order' => 100,
                    'content' => null,
                ],
            ],
            $serializedData
        );
        self::assertSame('<unknown custom="attribute">', $unknownElement->render());
    }

    public function testBlockLevelCachingFlow(): void
    {
        // Create mocks for block-level caching components
        $blockCache = $this->createMock(BlockHeadElementCacheInterface::class);
        $cacheDetector = $this->createMock(BlockCacheDetectorInterface::class);
        $elementTracker = $this->createMock(HeadElementTrackerInterface::class);

        // Mock a block
        $block = $this->createMock(AbstractBlock::class);
        $block->method('getNameInLayout')->willReturn('test_block');
        $block->method('getCacheKey')->willReturn('test_cache_key');

        // Test scenario: Block is cacheable and not cached yet (will be rendered)
        $cacheDetector->method('isBlockCacheable')->with($block)->willReturn(true);
        $cacheDetector->method('isBlockCached')->with($block)->willReturn(false);

        // Create test elements
        $newElements = [
            'meta_test' => $this->element('meta', ['attributes' => ['name' => 'test', 'content' => 'value']]),
        ];

        // Test tracker flow
        $elementTracker->expects($this->once())
            ->method('isBlockBeingTracked')
            ->with($block)
            ->willReturn(true);

        $elementTracker->expects($this->once())
            ->method('stopTrackingAndGetNewElements')
            ->with($block)
            ->willReturn($newElements);

        // Test cache saving
        $blockCache->expects($this->once())
            ->method('saveBlockHeadElements')
            ->with($block, $newElements);

        // Simulate the flow that would happen in BlockHtmlAfterObserver
        if ($elementTracker->isBlockBeingTracked($block)) {
            $trackedElements = $elementTracker->stopTrackingAndGetNewElements($block);

            if ($cacheDetector->isBlockCacheable($block)) {
                if (!$cacheDetector->isBlockCached($block) && !empty($trackedElements)) {
                    $blockCache->saveBlockHeadElements($block, $trackedElements);
                }
            }
        }
    }

    public function testBlockCacheRestoration(): void
    {
        // Test cache restoration flow
        $headTagManager = $this->createMock(HeadTagManagerInterface::class);
        $blockCache = $this->createMock(BlockHeadElementCacheInterface::class);

        // Mock a block
        $block = $this->createMock(AbstractBlock::class);
        $block->method('getNameInLayout')->willReturn('cached_block');
        $block->method('getCacheKey')->willReturn('cached_block_key');

        // Create cached elements
        $cachedMetaElement = $this->element('meta', ['attributes' => ['name' => 'cached', 'content' => 'data']]);
        $cachedElements = ['cached_meta' => $cachedMetaElement];

        // Mock cache loading
        $blockCache->expects($this->once())
            ->method('loadBlockHeadElements')
            ->with($block)
            ->willReturn($cachedElements);

        // Mock head tag manager restoration
        $headTagManager->expects($this->once())
            ->method('addElement')
            ->with($cachedMetaElement, 'cached_meta');

        // Simulate cache restoration
        $loadedElements = $blockCache->loadBlockHeadElements($block);

        if (!empty($loadedElements)) {
            foreach ($loadedElements as $key => $element) {
                $headTagManager->addElement($element, $key);
            }
        }

        self::assertEquals($cachedElements, $loadedElements);
    }
}
