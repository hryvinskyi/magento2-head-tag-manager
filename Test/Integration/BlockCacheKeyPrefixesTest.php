<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Integration;

use Hryvinskyi\HeadTagManager\Api\HeadTagManagerInterface;
use Magento\Cms\Block\Block as CmsBlock;
use Magento\Cms\Block\BlockByIdentifier;
use Magento\Framework\Cache\LockGuardedCacheLoader;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The container's lock-guarded loader wraps a load under every block cache key prefix the DI configuration lists,
 * the prefixes block classes define for themselves included, and leaves any other load as it is
 *
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class BlockCacheKeyPrefixesTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function blockCacheKeys(): array
    {
        return [
            'a key the block computes' => [AbstractBlock::CACHE_KEY_PREFIX . 'prefix_test'],
            'a custom cache key' => [AbstractBlock::CUSTOM_CACHE_KEY_PREFIX . 'prefix_test'],
            'a content block' => [CmsBlock::CACHE_KEY_PREFIX . 'prefix_test'],
            'a content block by identifier' => [BlockByIdentifier::CACHE_KEY_PREFIX . 'prefix_test'],
        ];
    }

    #[DataProvider('blockCacheKeys')]
    public function testAMissUnderABlockCacheKeyStoresTheElementsWithTheHtml(string $lockName): void
    {
        $saved = $this->missUnder($lockName);

        self::assertStringStartsWith('<div>block</div><!--hryvinskyi-head-elements:1:', $saved);
    }

    public function testAMissUnderAnyOtherLockNameStoresTheDataAsItIs(): void
    {
        self::assertSame('<div>block</div>', $this->missUnder('SYSTEM_CONFIG_prefix_test'));
    }

    /**
     * Run a miss through the container's loader: nothing cached, the lock free, a collector that adds a stylesheet
     *
     * @param string $lockName
     * @return string what the saver received
     */
    private function missUnder(string $lockName): string
    {
        $objectManager = Bootstrap::getObjectManager();
        $headTagManager = $objectManager->get(HeadTagManagerInterface::class);
        self::assertInstanceOf(HeadTagManagerInterface::class, $headTagManager);
        $headTagManager->clear();
        $loader = $objectManager->get(LockGuardedCacheLoader::class);
        self::assertInstanceOf(LockGuardedCacheLoader::class, $loader);
        $saved = null;

        $loader->lockedLoadData(
            $lockName,
            static fn (): bool => false,
            static function () use ($headTagManager): string {
                $headTagManager->addStylesheet('/css/prefix-test.css');

                return '<div>block</div>';
            },
            static function (mixed $data) use (&$saved): void {
                $saved = $data;
            }
        );

        self::assertIsString($saved);

        return $saved;
    }
}
