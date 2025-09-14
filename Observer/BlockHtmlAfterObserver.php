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
use Psr\Log\LoggerInterface;

class BlockHtmlAfterObserver implements ObserverInterface
{
    public function __construct(
        private readonly BlockCacheDetectorInterface $cacheDetector,
        private readonly HeadElementTrackerInterface $elementTracker,
        private readonly BlockHeadElementCacheInterface $blockCache,
        private readonly HeadTagManagerInterface $headTagManager,
        private readonly LoggerInterface $logger
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
        if (!$block instanceof MagentoAbstractBlock || !$block->getNameInLayout()) {
            return;
        }

        // Check if this block was being tracked
        if ($this->elementTracker->isBlockBeingTracked($block)) {
            // Get new head elements that were added during this block's rendering
            $newElements = $this->elementTracker->stopTrackingAndGetNewElements($block);

            // Check cache properties after rendering (when they are final)
            if ($this->cacheDetector->isBlockCacheable($block)) {
                // If block was served from cache, restore cached head elements
                if (!empty($newElements)) {
                    // Block was rendered, save new head elements to cache
                    $this->blockCache->saveBlockHeadElements($block, $newElements);
                    $this->logger->debug('Saved head elements for rendered block', [
                        'block_name' => $block->getNameInLayout(),
                        'elements_count' => count($newElements),
                        'tracking_level' => $this->elementTracker->getTrackingLevel()
                    ]);
                } else if ($this->cacheDetector->isBlockCached($block)) {
                    $this->restoreHeadElementsForBlock($block);
                    $this->logger->debug('Restored head elements from cache', [
                        'block_name' => $block->getNameInLayout()
                    ]);
                }
            }

            // Always clean up tracking for this block
            $this->elementTracker->clearTracking($block);
        }
    }

    /**
     * Restore head elements for a block from cache
     *
     * @param MagentoAbstractBlock $block
     * @return void
     */
    private function restoreHeadElementsForBlock(MagentoAbstractBlock $block): void
    {
        $elements = $this->blockCache->loadBlockHeadElements($block);

        if (count($elements) > 0) {
            foreach ($elements as $key => $element) {
                $this->headTagManager->addElement($element, $key);
            }
        }
    }
}