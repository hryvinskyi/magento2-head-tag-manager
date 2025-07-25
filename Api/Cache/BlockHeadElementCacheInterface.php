<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Api\Cache;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Magento\Framework\View\Element\AbstractBlock;

/**
 * Interface for managing block-specific head element caching
 */
interface BlockHeadElementCacheInterface
{
    /**
     * Save head elements for a specific block
     *
     * @param AbstractBlock $block
     * @param array $headElements
     * @return bool
     */
    public function saveBlockHeadElements(AbstractBlock $block, array $headElements): bool;

    /**
     * Load head elements for a specific block
     *
     * @param AbstractBlock $block
     * @return HeadElementInterface[]
     */
    public function loadBlockHeadElements(AbstractBlock $block): array;

    /**
     * Clear head elements cache for a specific block
     *
     * @param AbstractBlock $block
     * @return bool
     */
    public function clearBlockHeadElements(AbstractBlock $block): bool;
}
