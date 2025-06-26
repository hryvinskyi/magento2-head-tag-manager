<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model\Cache;

use Hryvinskyi\HeadTagManager\Api\Cache\HeadElementCacheStrategyInterface;

/**
 * Null cache strategy - disables caching
 */
class NullCacheStrategy implements HeadElementCacheStrategyInterface
{
    /**
     * @inheritDoc
     */
    public function load(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function save(array $elements): bool
    {
        return true; // Always successful but does nothing
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
    {
        return true; // Always successful but does nothing
    }

    /**
     * @inheritDoc
     */
    public function isEnabled(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function getCacheTags(): array
    {
        return []; // No cache tags for null strategy
    }

    /**
     * @inheritDoc
     */
    public function getCacheKey(): string
    {
        return 'null_cache_strategy'; // Unique key for this strategy
    }
}