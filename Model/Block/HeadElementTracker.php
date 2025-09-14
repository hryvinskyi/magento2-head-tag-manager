<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model\Block;

use Hryvinskyi\HeadTagManager\Api\Block\HeadElementTrackerInterface;
use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Api\HeadTagManagerInterface;
use Hryvinskyi\HeadTagManager\Api\Serializer\HeadElementSerializerInterface;
use Magento\Framework\View\Element\AbstractBlock;
use Psr\Log\LoggerInterface;

/**
 * Service for tracking head element changes during block rendering
 */
class HeadElementTracker implements HeadElementTrackerInterface
{
    private array $trackingStack = [];
    private int $trackingLevel = 0;

    public function __construct(
        private readonly HeadTagManagerInterface $headTagManager,
        private readonly HeadElementSerializerInterface $elementSerializer,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function startTracking(AbstractBlock $block): void
    {
        $blockName = $block->getNameInLayout();
        if (!$blockName) {
            return;
        }

        // Push current state onto the tracking stack
        $this->trackingStack[] = [
            'block_name' => $blockName,
            'level' => $this->trackingLevel,
            'elements_before' => array_keys($this->headTagManager->getAllElements())
        ];

        $this->trackingLevel++;

        $this->logger->debug('Started tracking head elements for block', [
            'block_name' => $blockName,
            'level' => $this->trackingLevel - 1,
            'initial_elements_count' => count(end($this->trackingStack)['elements_before'])
        ]);
    }

    /**
     * @inheritDoc
     */
    public function stopTrackingAndGetNewElements(AbstractBlock $block): array
    {
        $blockName = $block->getNameInLayout();
        if (!$blockName || empty($this->trackingStack)) {
            return [];
        }

        // Find the matching tracking entry for this block
        $trackingEntry = null;
        for ($i = count($this->trackingStack) - 1; $i >= 0; $i--) {
            if ($this->trackingStack[$i]['block_name'] === $blockName) {
                $trackingEntry = $this->trackingStack[$i];
                break;
            }
        }

        if (!$trackingEntry) {
            return [];
        }

        try {
            $previousElementKeys = $trackingEntry['elements_before'];
            $currentElements = $this->headTagManager->getAllElements();

            $newElementsData = [];
            foreach ($currentElements as $key => $element) {
                if (!in_array($key, $previousElementKeys, true)) {
                    $newElementsData[$key] = $element;
                }
            }

            if (count($newElementsData) > 0) {
                $newElementsData = $this->elementSerializer->serialize($newElementsData);
            }

            $this->logger->debug('Stopped tracking head elements for block', [
                'block_name' => $blockName,
                'level' => $trackingEntry['level'],
                'new_elements_count' => count($newElementsData)
            ]);

            return $newElementsData;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to get new head elements for block', [
                'block_name' => $blockName,
                'exception' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * @inheritDoc
     */
    public function clearTracking(AbstractBlock $block): void
    {
        $blockName = $block->getNameInLayout();
        if (!$blockName) {
            return;
        }

        // Remove the tracking entry for this block and decrease level
        for ($i = count($this->trackingStack) - 1; $i >= 0; $i--) {
            if ($this->trackingStack[$i]['block_name'] === $blockName) {
                array_splice($this->trackingStack, $i, 1);
                $this->trackingLevel = max(0, $this->trackingLevel - 1);
                break;
            }
        }

        $this->logger->debug('Cleared tracking for block', [
            'block_name' => $blockName,
            'remaining_level' => $this->trackingLevel
        ]);
    }

    /**
     * @inheritDoc
     */
    public function getCurrentTrackingBlock(): ?string
    {
        if (empty($this->trackingStack)) {
            return null;
        }

        // Return the most recent (top-level) tracking block
        return end($this->trackingStack)['block_name'];
    }

    /**
     * Check if a block is currently being tracked
     *
     * @param AbstractBlock $block
     * @return bool
     */
    public function isBlockBeingTracked(AbstractBlock $block): bool
    {
        $blockName = $block->getNameInLayout();
        if (!$blockName) {
            return false;
        }

        foreach ($this->trackingStack as $entry) {
            if ($entry['block_name'] === $blockName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get current tracking level
     *
     * @return int
     */
    public function getTrackingLevel(): int
    {
        return $this->trackingLevel;
    }
}
