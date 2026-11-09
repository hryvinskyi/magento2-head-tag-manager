<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Api\Cache;

/**
 * Wraps the three actions of one block HTML load so the block's head elements are stored in and restored from the
 * block's own cache entry
 */
interface BlockHtmlCacheLoaderInterface
{
    /**
     * Wrap the load, collect and save actions of one block HTML load
     *
     * The wrapped actions share the state of this one load and nothing else: the collector records the elements
     * added while the block renders, the saver stores them with the HTML, and the loader restores them from a hit
     * and answers a miss for an entry that does not carry them.
     *
     * @param callable $loader reads the block's entry, false when there is none
     * @param callable $collector renders the block's HTML
     * @param callable $saver stores what the collector rendered
     * @return array{loader: callable, collector: callable, saver: callable}
     */
    public function wrap(callable $loader, callable $collector, callable $saver): array;
}
