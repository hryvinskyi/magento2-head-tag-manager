<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Observer;

use Hryvinskyi\HeadTagManager\Api\Block\BlockCacheDetectorInterface;
use Hryvinskyi\HeadTagManager\Api\Block\HeadElementTrackerInterface;
use Hryvinskyi\HeadTagManager\Api\Cache\BlockHeadElementCacheInterface;
use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Api\HeadTagManagerInterface;
use Hryvinskyi\HeadTagManager\Observer\BlockHtmlAfterObserver;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\View\Element\AbstractBlock;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BlockHtmlAfterObserverTest extends TestCase
{
    private $cacheDetector;
    private $elementTracker;
    private $blockCache;
    private $headTagManager;
    private $logger;
    private $observer;
    private $event;
    private $block;
    private $blockHtmlAfterObserver;

    protected function setUp(): void
    {
        $this->cacheDetector = $this->createMock(BlockCacheDetectorInterface::class);
        $this->elementTracker = $this->createMock(HeadElementTrackerInterface::class);
        $this->blockCache = $this->createMock(BlockHeadElementCacheInterface::class);
        $this->headTagManager = $this->createMock(HeadTagManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->observer = $this->createMock(Observer::class);
        $this->event = $this->createMock(Event::class);
        $this->block = $this->createMock(AbstractBlock::class);

        $this->observer->method('getEvent')->willReturn($this->event);
        $this->event->method('getBlock')->willReturn($this->block);

        $this->blockHtmlAfterObserver = new BlockHtmlAfterObserver(
            $this->cacheDetector,
            $this->elementTracker,
            $this->blockCache,
            $this->headTagManager,
            $this->logger
        );
    }

    public function testExecuteSkipsNonAbstractBlocks()
    {
        $this->event->method('getBlock')->willReturn(new \stdClass());

        $this->elementTracker->expects($this->never())
            ->method('isBlockBeingTracked');

        $this->blockHtmlAfterObserver->execute($this->observer);
    }

    public function testExecuteSkipsBlocksWithoutLayoutName()
    {
        $this->block->method('getNameInLayout')->willReturn(null);

        $this->elementTracker->expects($this->never())
            ->method('isBlockBeingTracked');

        $this->blockHtmlAfterObserver->execute($this->observer);
    }

    public function testExecuteSkipsNonTrackedBlocks()
    {
        $this->block->method('getNameInLayout')->willReturn('test_block');

        $this->elementTracker->expects($this->once())
            ->method('isBlockBeingTracked')
            ->with($this->block)
            ->willReturn(false);

        $this->elementTracker->expects($this->never())
            ->method('stopTrackingAndGetNewElements');

        $this->blockHtmlAfterObserver->execute($this->observer);
    }

    public function testExecuteSavesNewElementsForCacheableRenderedBlock()
    {
        $this->block->method('getNameInLayout')->willReturn('test_block');

        $newElements = ['element1' => $this->createMock(HeadElementInterface::class)];

        $this->elementTracker->expects($this->once())
            ->method('isBlockBeingTracked')
            ->with($this->block)
            ->willReturn(true);

        $this->elementTracker->expects($this->once())
            ->method('stopTrackingAndGetNewElements')
            ->with($this->block)
            ->willReturn($newElements);

        $this->cacheDetector->expects($this->once())
            ->method('isBlockCacheable')
            ->with($this->block)
            ->willReturn(true);

        // Note: isBlockCached is called conditionally, so we don't set strict expectations

        $this->blockCache->expects($this->once())
            ->method('saveBlockHeadElements')
            ->with($this->block, $newElements);

        $this->elementTracker->expects($this->once())
            ->method('clearTracking')
            ->with($this->block);

        $this->elementTracker->expects($this->once())
            ->method('getTrackingLevel')
            ->willReturn(1);

        $this->logger->expects($this->once())
            ->method('debug')
            ->with(
                'Saved head elements for rendered block',
                $this->callback(function ($context) {
                    return isset($context['block_name']) &&
                           isset($context['elements_count']) &&
                           isset($context['tracking_level']);
                })
            );

        $this->blockHtmlAfterObserver->execute($this->observer);
    }

    public function testExecuteRestoresElementsForCachedBlock()
    {
        $this->block->method('getNameInLayout')->willReturn('test_block');

        $cachedElements = ['cached1' => $this->createMock(HeadElementInterface::class)];

        $this->elementTracker->expects($this->once())
            ->method('isBlockBeingTracked')
            ->with($this->block)
            ->willReturn(true);

        $this->elementTracker->expects($this->once())
            ->method('stopTrackingAndGetNewElements')
            ->with($this->block)
            ->willReturn([]); // No new elements

        $this->cacheDetector->expects($this->once())
            ->method('isBlockCacheable')
            ->with($this->block)
            ->willReturn(true);

        $this->cacheDetector->expects($this->once())
            ->method('isBlockCached')
            ->with($this->block)
            ->willReturn(true);

        $this->blockCache->expects($this->once())
            ->method('loadBlockHeadElements')
            ->with($this->block)
            ->willReturn($cachedElements);

        $this->headTagManager->expects($this->once())
            ->method('addElement')
            ->with($cachedElements['cached1'], 'cached1');

        $this->elementTracker->expects($this->once())
            ->method('clearTracking')
            ->with($this->block);

        $this->logger->expects($this->once())
            ->method('debug')
            ->with(
                'Restored head elements from cache',
                ['block_name' => 'test_block']
            );

        $this->blockHtmlAfterObserver->execute($this->observer);
    }

    public function testExecuteSkipsCachingForNonCacheableBlock()
    {
        $this->block->method('getNameInLayout')->willReturn('test_block');

        $newElements = ['element1' => $this->createMock(HeadElementInterface::class)];

        $this->elementTracker->expects($this->once())
            ->method('isBlockBeingTracked')
            ->with($this->block)
            ->willReturn(true);

        $this->elementTracker->expects($this->once())
            ->method('stopTrackingAndGetNewElements')
            ->with($this->block)
            ->willReturn($newElements);

        $this->cacheDetector->expects($this->once())
            ->method('isBlockCacheable')
            ->with($this->block)
            ->willReturn(false);

        $this->blockCache->expects($this->never())
            ->method('saveBlockHeadElements');

        $this->blockCache->expects($this->never())
            ->method('loadBlockHeadElements');

        $this->elementTracker->expects($this->once())
            ->method('clearTracking')
            ->with($this->block);

        $this->blockHtmlAfterObserver->execute($this->observer);
    }
}