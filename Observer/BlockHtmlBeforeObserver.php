<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Observer;

use Hryvinskyi\HeadTagManager\Api\Block\HeadElementTrackerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\Element\AbstractBlock as MagentoAbstractBlock;
use Magento\Store\Model\ScopeInterface;

class BlockHtmlBeforeObserver implements ObserverInterface
{
    public function __construct(
        private readonly HeadElementTrackerInterface $elementTracker,
        private readonly ScopeConfigInterface $scopeConfig
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
        if (!$block instanceof MagentoAbstractBlock || !$block->getNameInLayout()) {
            return;
        }

        // Skip tracking if module output is disabled (prevents tracking imbalance)
        if ($this->isModuleOutputDisabled($block)) {
            return;
        }

        // Always start tracking for all blocks
        // All cache handling will be done in the After observer where cache properties are final
        $this->elementTracker->startTracking($block);
    }

    /**
     * Check if module output is disabled for this block
     *
     * @param MagentoAbstractBlock $block
     * @return bool
     */
    private function isModuleOutputDisabled(MagentoAbstractBlock $block): bool
    {
        return $this->scopeConfig->isSetFlag(
            'advanced/modules_disable_output/' . $block->getModuleName(),
            ScopeInterface::SCOPE_STORE
        );
    }
}