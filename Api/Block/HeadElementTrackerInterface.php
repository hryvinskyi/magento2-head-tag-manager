<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Api\Block;

use Magento\Framework\View\Element\AbstractBlock;

/**
 * Interface for tracking head element changes during block rendering
 */
interface HeadElementTrackerInterface
{
    /**
     * Start tracking head elements for a block
     *
     * @param AbstractBlock $block
     * @return void
     */
    public function startTracking(AbstractBlock $block): void;

    /**
     * Stop tracking and return new head elements added during block rendering
     *
     * @param AbstractBlock $block
     * @return array
     */
    public function stopTrackingAndGetNewElements(AbstractBlock $block): array;

    /**
     * Clear tracking data for a block
     *
     * @param AbstractBlock $block
     * @return void
     */
    public function clearTracking(AbstractBlock $block): void;

    /**
     * Get the currently tracked block name
     *
     * @return string|null
     */
    public function getCurrentTrackingBlock(): ?string;
}
