<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Plugin\Framework\Cache;

use Hryvinskyi\HeadTagManager\Api\Cache\BlockHtmlCacheLoaderInterface;
use Hryvinskyi\HeadTagManager\Plugin\Framework\Cache\LockGuardedCacheLoader;
use Magento\Framework\Cache\LockGuardedCacheLoader as FrameworkLockGuardedCacheLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LockGuardedCacheLoaderTest extends TestCase
{
    /**
     * @var BlockHtmlCacheLoaderInterface&MockObject
     */
    private MockObject $cacheLoader;

    /**
     * @var LockGuardedCacheLoader
     */
    private LockGuardedCacheLoader $plugin;

    /**
     * @var FrameworkLockGuardedCacheLoader
     */
    private FrameworkLockGuardedCacheLoader $subject;

    protected function setUp(): void
    {
        $this->cacheLoader = $this->createMock(BlockHtmlCacheLoaderInterface::class);
        $this->plugin = new LockGuardedCacheLoader(
            $this->cacheLoader,
            ['block' => 'BLOCK_', 'custom_block' => 'CUSTOM_BLOCK_', 'content_block' => 'CMS_BLOCK']
        );
        $this->subject = $this->createStub(FrameworkLockGuardedCacheLoader::class);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function lockNamesOfOtherLoads(): array
    {
        return [
            'the configuration cache' => ['SYSTEM_CONFIG'],
            'a configuration cache scope' => ['SYSTEM_CONFIG_scopes'],
            'a lock of its own' => ['some_lock'],
            'a lower-case block prefix' => ['block_promo'],
            'the prefix inside the name' => ['MY_BLOCK_promo'],
            'an empty name' => [''],
            'a prefix that is not configured' => ['PRODUCT_LIST_0f4e2c'],
        ];
    }

    #[DataProvider('lockNamesOfOtherLoads')]
    public function testALoadUnderAnyOtherLockNameKeepsItsArguments(string $lockName): void
    {
        $this->cacheLoader->expects(self::never())->method('wrap');

        self::assertNull($this->plugin->beforeLockedLoadData(
            $this->subject,
            $lockName,
            static fn (): bool => false,
            static fn (): string => 'config',
            static function (mixed $data): void {
            }
        ));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function blockHtmlCacheKeys(): array
    {
        return [
            'a key the block computes' => ['BLOCK_0f4e2c'],
            'a custom cache key' => ['CUSTOM_BLOCK_promo'],
            'a block class with a prefix of its own' => ['CMS_BLOCK_0f4e2c'],
            'a block class with a prefix of its own, without the separator' => ['CMS_BLOCK0f4e2c'],
        ];
    }

    #[DataProvider('blockHtmlCacheKeys')]
    public function testEveryLoadUnderABlockHtmlCacheKeyGetsTheWrappedActions(string $lockName): void
    {
        $loader = static fn (): bool => false;
        $collector = static fn (): string => '<div></div>';
        $saver = static function (mixed $data): void {
        };
        $wrappedLoader = static fn (): string => 'wrapped';
        $wrappedCollector = static fn (): string => 'wrapped';
        $wrappedSaver = static function (mixed $data): void {
        };
        $this->cacheLoader->expects(self::exactly(2))
            ->method('wrap')
            ->with($loader, $collector, $saver)
            ->willReturn(['loader' => $wrappedLoader, 'collector' => $wrappedCollector, 'saver' => $wrappedSaver]);

        $expected = [$lockName, $wrappedLoader, $wrappedCollector, $wrappedSaver];
        self::assertSame(
            $expected,
            $this->plugin->beforeLockedLoadData($this->subject, $lockName, $loader, $collector, $saver)
        );
        self::assertSame(
            $expected,
            $this->plugin->beforeLockedLoadData($this->subject, $lockName, $loader, $collector, $saver)
        );
    }
}
