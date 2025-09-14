<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Observer;

use Hryvinskyi\HeadTagManager\Api\Block\HeadElementTrackerInterface;
use Hryvinskyi\HeadTagManager\Observer\BlockHtmlBeforeObserver;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\TestCase;

class BlockHtmlBeforeObserverTest extends TestCase
{
    private $elementTracker;
    private $scopeConfig;
    private $observer;
    private $event;
    private $block;
    private $blockHtmlBeforeObserver;

    protected function setUp(): void
    {
        $this->elementTracker = $this->createMock(HeadElementTrackerInterface::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->observer = $this->createMock(Observer::class);
        $this->event = $this->createMock(Event::class);
        $this->block = $this->createMock(AbstractBlock::class);

        $this->observer->method('getEvent')->willReturn($this->event);
        $this->event->method('getBlock')->willReturn($this->block);

        $this->blockHtmlBeforeObserver = new BlockHtmlBeforeObserver(
            $this->elementTracker,
            $this->scopeConfig
        );
    }

    public function testExecuteSkipsNonAbstractBlocks()
    {
        $this->event->method('getBlock')->willReturn(new \stdClass());

        $this->elementTracker->expects($this->never())
            ->method('startTracking');

        $this->blockHtmlBeforeObserver->execute($this->observer);
    }

    public function testExecuteSkipsBlocksWithoutLayoutName()
    {
        $this->block->method('getNameInLayout')->willReturn(null);

        $this->elementTracker->expects($this->never())
            ->method('startTracking');

        $this->blockHtmlBeforeObserver->execute($this->observer);
    }

    public function testExecuteSkipsDisabledModules()
    {
        $this->block->method('getNameInLayout')->willReturn('test_block');
        $this->block->method('getModuleName')->willReturn('Magento_TestModule');

        $this->scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with(
                'advanced/modules_disable_output/Magento_TestModule',
                ScopeInterface::SCOPE_STORE
            )
            ->willReturn(true);

        $this->elementTracker->expects($this->never())
            ->method('startTracking');

        $this->blockHtmlBeforeObserver->execute($this->observer);
    }

    public function testExecuteStartsTrackingForValidBlock()
    {
        $this->block->method('getNameInLayout')->willReturn('test_block');
        $this->block->method('getModuleName')->willReturn('Magento_TestModule');

        $this->scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with(
                'advanced/modules_disable_output/Magento_TestModule',
                ScopeInterface::SCOPE_STORE
            )
            ->willReturn(false);

        $this->elementTracker->expects($this->once())
            ->method('startTracking')
            ->with($this->block);

        $this->blockHtmlBeforeObserver->execute($this->observer);
    }
}