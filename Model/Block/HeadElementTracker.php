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
    private array $elementsBefore = [];
    private ?string $currentTrackingBlock = null;

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
        $this->currentTrackingBlock = $blockName;

        $this->elementsBefore[$blockName] = array_keys($this->headTagManager->getAllElements());

        $this->logger->debug('Started tracking head elements for block', [
            'block_name' => $blockName,
            'initial_elements_count' => count($this->elementsBefore[$blockName])
        ]);
    }

    /**
     * @inheritDoc
     */
    public function stopTrackingAndGetNewElements(AbstractBlock $block): array
    {
        $blockName = $block->getNameInLayout();
        if (!$blockName || !isset($this->elementsBefore[$blockName])) {
            return [];
        }

        try {
            $previousElementKeys = $this->elementsBefore[$blockName];
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
        $this->currentTrackingBlock = null;
        $blockName = $block->getNameInLayout();
        if ($blockName && isset($this->elementsBefore[$blockName])) {
            unset($this->elementsBefore[$blockName]);
        }
    }

    /**
     * @inheritDoc
     */
    public function getCurrentTrackingBlock(): ?string
    {
        return $this->currentTrackingBlock;
    }
}
