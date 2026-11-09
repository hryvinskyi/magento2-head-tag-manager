<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Plugin\Framework\Cache;

use Hryvinskyi\HeadTagManager\Model\Cache\BlockHtmlCacheLoader;
use Hryvinskyi\HeadTagManager\Model\Cache\CachedBlockHtmlCodec;
use Hryvinskyi\HeadTagManager\Model\HeadTagManager;
use Hryvinskyi\HeadTagManager\Plugin\Framework\Cache\LockGuardedCacheLoader;
use Hryvinskyi\HeadTagManager\Test\Unit\Model\Cache\Fixture\RealHeadElements;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Cache\LockGuardedCacheLoader as FrameworkLockGuardedCacheLoader;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\View\Element\AbstractBlock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Every path of the framework's real lock-guarded loader, driven through the arguments the plugin hands it
 *
 * The deadline and parallel-generation paths cannot be reached against a real lock without waiting out the
 * framework's ten-second deadline, so the lock manager is a fake that grants or refuses as each test needs.
 */
class LockedLoadPathsTest extends TestCase
{
    use RealHeadElements;

    private const KEY = 'CUSTOM_BLOCK_promo';

    /**
     * @var HeadTagManager
     */
    private HeadTagManager $manager;

    /**
     * @var CachedBlockHtmlCodec
     */
    private CachedBlockHtmlCodec $codec;

    /**
     * @var LockGuardedCacheLoader
     */
    private LockGuardedCacheLoader $plugin;

    /**
     * @var list<string|false> what the load action answers, one entry per call; once the list is exhausted it
     *     answers what the save action stored last, or false when nothing was stored
     */
    private array $entries = [];

    /**
     * @var int
     */
    private int $loads = 0;

    /**
     * @var int
     */
    private int $collects = 0;

    /**
     * @var list<mixed>
     */
    private array $saved = [];

    protected function setUp(): void
    {
        $this->manager = $this->newHeadTagManager();
        $this->codec = $this->blockHtmlCodec();
        $this->plugin = new LockGuardedCacheLoader(
            new BlockHtmlCacheLoader($this->codec, $this->manager, new NullLogger()),
            ['block' => AbstractBlock::CACHE_KEY_PREFIX, 'custom_block' => AbstractBlock::CUSTOM_CACHE_KEY_PREFIX]
        );
    }

    /**
     * The framework's loader with a lock manager that grants or refuses every lock
     *
     * @param bool $lockGranted
     * @param bool $parallelGeneration whether the deployment allows generating without the lock
     * @param int $loadTimeout milliseconds until the loader stops waiting and renders without saving
     * @return FrameworkLockGuardedCacheLoader
     */
    private function frameworkLoader(
        bool $lockGranted,
        bool $parallelGeneration = false,
        int $loadTimeout = 10000
    ): FrameworkLockGuardedCacheLoader {
        $locker = $this->createStub(LockManagerInterface::class);
        $locker->method('lock')->willReturn($lockGranted);
        $locker->method('unlock')->willReturn(true);
        // One framework release reads the setting by its path, an earlier one reads the whole cache section.
        $deploymentConfig = $this->createStub(DeploymentConfig::class);
        $deploymentConfig->method('get')->willReturnCallback(
            static fn (?string $path = null): ?bool => $path === 'cache/allow_parallel_generation'
                ? $parallelGeneration
                : null
        );
        $deploymentConfig->method('getConfigData')->willReturnCallback(
            static fn (?string $section = null): ?array => $section === 'cache'
                ? ['allow_parallel_generation' => $parallelGeneration]
                : null
        );

        return new FrameworkLockGuardedCacheLoader($locker, 10000, 1, $loadTimeout, 1, $deploymentConfig);
    }

    /**
     * Run one block HTML load the way the block does: through the plugin into the loader
     *
     * @param FrameworkLockGuardedCacheLoader $loader
     * @param mixed $rendered what the block's rendering returns
     * @return mixed what the loader answers the block
     */
    private function loadBlockHtml(FrameworkLockGuardedCacheLoader $loader, mixed $rendered = '<div>promo</div>'): mixed
    {
        $arguments = $this->plugin->beforeLockedLoadData(
            $loader,
            self::KEY,
            function (): mixed {
                return $this->entries[$this->loads++] ?? ($this->saved === [] ? false : end($this->saved));
            },
            function () use ($rendered): mixed {
                $this->collects++;
                $this->manager->addStylesheet('/promo.css', [], 'promo_css');

                return $rendered;
            },
            function (mixed $data): void {
                $this->saved[] = $data;
            }
        );
        self::assertNotNull($arguments);

        return $loader->lockedLoadData(...$arguments);
    }

    /**
     * An entry as a previous render stored it
     *
     * @return string
     */
    private function markedEntry(): string
    {
        $manager = $this->newHeadTagManager();
        $manager->addStylesheet('/promo.css', [], 'promo_css');

        return $this->codec->encode('<div>promo</div>', $manager->getAllElements());
    }

    public function testAnOptimisticHitRestoresTheElementsOnce(): void
    {
        $this->entries = [$this->markedEntry()];

        self::assertSame('<div>promo</div>', $this->loadBlockHtml($this->frameworkLoader(true)));
        self::assertSame(0, $this->collects);
        self::assertSame([], $this->saved);
        self::assertSame(['promo_css'], array_keys($this->manager->getAllElements()));
    }

    public function testAMissUnderTheLockRendersOnceAndSavesTheMarkedEntry(): void
    {
        self::assertSame('<div>promo</div>', $this->loadBlockHtml($this->frameworkLoader(true)));

        self::assertSame(1, $this->collects);
        self::assertCount(1, $this->saved);
        self::assertIsString($this->saved[0]);
        $decoded = $this->codec->decode($this->saved[0]);
        self::assertNotNull($decoded);
        self::assertSame('<div>promo</div>', $decoded['html']);
        self::assertSame(['promo_css'], array_keys($decoded['elements']));
    }

    public function testARefusedLockThenAnEntryAnotherProcessSavedRestoresItWithoutRendering(): void
    {
        $this->entries = [false, $this->markedEntry()];

        self::assertSame('<div>promo</div>', $this->loadBlockHtml($this->frameworkLoader(false)));

        self::assertSame(2, $this->loads);
        self::assertSame(0, $this->collects);
        self::assertSame([], $this->saved);
        self::assertSame(['promo_css'], array_keys($this->manager->getAllElements()));
    }

    public function testARefusedLockPastTheDeadlineRendersWithoutSaving(): void
    {
        self::assertSame('<div>promo</div>', $this->loadBlockHtml($this->frameworkLoader(false, false, 0)));

        self::assertSame(1, $this->collects);
        self::assertSame([], $this->saved);
        self::assertSame(['promo_css'], array_keys($this->manager->getAllElements()));
    }

    public function testARefusedLockWithParallelGenerationRendersWithoutSaving(): void
    {
        self::assertSame('<div>promo</div>', $this->loadBlockHtml($this->frameworkLoader(false, true)));

        self::assertSame(1, $this->collects);
        self::assertSame([], $this->saved);
        self::assertSame(['promo_css'], array_keys($this->manager->getAllElements()));
    }

    public function testAnUnmarkedEntryRendersUnderTheLockAndIsOverwrittenWithTheMarker(): void
    {
        $this->entries = ['<div>promo</div>'];

        self::assertSame('<div>promo</div>', $this->loadBlockHtml($this->frameworkLoader(true)));

        self::assertSame(1, $this->collects);
        self::assertCount(1, $this->saved);
        self::assertIsString($this->saved[0]);
        self::assertNotNull($this->codec->decode($this->saved[0]));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function rendersThatAreNoText(): array
    {
        return [
            'false' => [false],
            'null' => [null],
        ];
    }

    #[DataProvider('rendersThatAreNoText')]
    public function testARenderThatIsNoTextIsStoredAsAMarkedEmptyEntryAndServedOnTheNextLoad(mixed $rendered): void
    {
        $loader = $this->frameworkLoader(true);

        // The block casts the answer to text: a null render answers null, a false one the stored empty HTML.
        self::assertContains($this->loadBlockHtml($loader, $rendered), ['', null]);
        self::assertSame(1, $this->collects);
        self::assertCount(1, $this->saved);
        self::assertIsString($this->saved[0]);
        $decoded = $this->codec->decode($this->saved[0]);
        self::assertNotNull($decoded);
        self::assertSame('', $decoded['html']);
        self::assertSame(['promo_css'], array_keys($decoded['elements']));

        $this->manager->clear();
        self::assertSame('', $this->loadBlockHtml($loader, $rendered));

        self::assertSame(1, $this->collects, 'The next load must be served from the entry.');
        self::assertCount(1, $this->saved);
        self::assertSame(['promo_css'], array_keys($this->manager->getAllElements()));
    }
}
