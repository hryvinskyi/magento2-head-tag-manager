<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Observer;

use Hryvinskyi\HeadTagManager\Api\Block\BlockCacheDetectorInterface;
use Hryvinskyi\HeadTagManager\Api\Block\HeadElementTrackerInterface;
use Hryvinskyi\HeadTagManager\Api\Cache\BlockHeadElementCacheInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\Element\AbstractBlock as MagentoAbstractBlock;

class BlockHtmlAfterObserver implements ObserverInterface
{
    public function __construct(
        private readonly BlockCacheDetectorInterface $cacheDetector,
        private readonly HeadElementTrackerInterface $elementTracker,
        private readonly BlockHeadElementCacheInterface $blockCache,
    ) {
    }

    /**
     * Around toHtml to handle both direct block caching and parent block caching
     *
     * @param Observer $observer
     * @return void
     */

    public function execute(Observer $observer): void
    {
        $block = $observer->getEvent()->getBlock();
        // Only handle AbstractBlock instances that have layout names
        if (!$block instanceof MagentoAbstractBlock || !$block->getNameInLayout() || !$this->cacheDetector->isBlockCacheable($block)) {
            return;
        }


        if ($this->elementTracker->getCurrentTrackingBlock() === $block->getNameInLayout()) {
            // Block was executed (not cached), save new head elements
            $newElements = $this->elementTracker->stopTrackingAndGetNewElements($block);
            $this->blockCache->saveBlockHeadElements($block, $newElements);

            // Always clean up tracking
            $this->elementTracker->clearTracking($block);
        }
    }
}