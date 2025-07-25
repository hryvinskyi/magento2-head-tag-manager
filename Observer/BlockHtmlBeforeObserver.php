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
use Hryvinskyi\HeadTagManager\Api\HeadTagManagerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\Element\AbstractBlock as MagentoAbstractBlock;

class BlockHtmlBeforeObserver implements ObserverInterface
{
    public function __construct(
        private readonly BlockCacheDetectorInterface $cacheDetector,
        private readonly HeadElementTrackerInterface $elementTracker,
        private readonly BlockHeadElementCacheInterface $blockCache,
        private readonly HeadTagManagerInterface $headTagManager
    ) {
    }

    /**
     * Observer toHtml to handle both direct block caching and parent block caching
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

        if ($this->elementTracker->getCurrentTrackingBlock() === null) {
            // Check if block will be served from cache
            if ($this->cacheDetector->isBlockCached($block) === false) {
                // Block will execute normally, start tracking
                $this->elementTracker->startTracking($block);
            } else {
                // Block will load from cache, restore head elements for this block and its children
                $this->restoreHeadElementsForBlockAndChildren($block);
            }
        }
    }

    /**
     * Restore head elements for a block and all its children recursively
     *
     * @param MagentoAbstractBlock $block
     * @return void
     */
    private function restoreHeadElementsForBlockAndChildren(MagentoAbstractBlock $block): void
    {
        // Restore head elements for the current block
        $elements = $this->blockCache->loadBlockHeadElements($block);

        if (count($elements) > 0) {
            foreach ($elements as $key => $element) {
                $this->headTagManager->addElement($element, $key);
            }
        }
    }
}