<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Api\Cache;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;

interface HeadElementCacheStrategyInterface
{
    /**
     * Load elements from cache
     *
     * @return HeadElementInterface[]
     */
    public function load(): array;

    /**
     * Save elements to cache
     *
     * @param HeadElementInterface[] $elements
     * @return bool
     */
    public function save(array $elements): bool;

    /**
     * Clear elements from cache
     *
     * @return bool
     */
    public function clear(): bool;

    /**
     * Check if cache is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool;
}