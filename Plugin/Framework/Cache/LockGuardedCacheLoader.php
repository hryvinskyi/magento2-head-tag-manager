<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Plugin\Framework\Cache;

use Hryvinskyi\HeadTagManager\Api\Cache\BlockHtmlCacheLoaderInterface;
use Magento\Framework\Cache\LockGuardedCacheLoader as FrameworkLockGuardedCacheLoader;

/**
 * Hands the lock-guarded loader the wrapped actions of a block HTML load, so the block's head elements are stored
 * and restored inside its own cache entry
 *
 * A block loads its HTML through this loader under its cache key as the lock name, and a block cache key starts with
 * the prefix of the block's class: the framework's for a key the block computes and for a custom key, or one a block
 * class defines for itself, as the content blocks of the CMS module do. The prefixes come from the DI configuration,
 * so a module whose blocks define another one adds it there. The same loader serves the configuration cache and
 * anything else that wants a lock around a cache miss, under names that start with none of them; those calls keep
 * their arguments. The name alone decides, so a block created outside the layout, without a name, is wrapped like any
 * other, and nothing has to be computed or remembered per block.
 *
 * The plugin runs in every area: a block rendered outside the storefront, in an e-mail sent by cron for instance,
 * reads the same entries and must strip the elements from its HTML too.
 *
 * The first call comes from the configuration cache during bootstrap, so the loader service is injected through a
 * generated proxy and its graph is only built once a block HTML load is actually wrapped.
 */
class LockGuardedCacheLoader
{
    /**
     * @param BlockHtmlCacheLoaderInterface $cacheLoader wraps the actions of a block HTML load
     * @param array<string,string> $blockCacheKeyPrefixes the prefixes block cache keys start with, by name
     */
    public function __construct(
        private readonly BlockHtmlCacheLoaderInterface $cacheLoader,
        private readonly array $blockCacheKeyPrefixes = []
    ) {
    }

    /**
     * Replace the actions of a block HTML load with the wrapped ones
     *
     * @param FrameworkLockGuardedCacheLoader $subject the loader, which the plugin does not call
     * @param string $lockName
     * @param callable $dataLoader
     * @param callable $dataCollector
     * @param callable $dataSaver
     * @return array{string, callable, callable, callable}|null the new arguments, or null to keep the given ones
     */
    public function beforeLockedLoadData(
        FrameworkLockGuardedCacheLoader $subject,
        string $lockName,
        callable $dataLoader,
        callable $dataCollector,
        callable $dataSaver
    ): ?array {
        if (!$this->isBlockHtmlCacheKey($lockName)) {
            return null;
        }

        $wrapped = $this->cacheLoader->wrap($dataLoader, $dataCollector, $dataSaver);

        return [$lockName, $wrapped['loader'], $wrapped['collector'], $wrapped['saver']];
    }

    /**
     * Whether the lock name is a block HTML cache key
     *
     * @param string $lockName
     * @return bool
     */
    private function isBlockHtmlCacheKey(string $lockName): bool
    {
        foreach ($this->blockCacheKeyPrefixes as $prefix) {
            if ($prefix !== '' && str_starts_with($lockName, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
