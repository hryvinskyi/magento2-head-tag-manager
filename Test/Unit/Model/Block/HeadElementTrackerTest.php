<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\Block;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Model\HeadTagManager;
use Hryvinskyi\HeadTagManager\Api\Serializer\HeadElementSerializerInterface;
use Hryvinskyi\HeadTagManager\Model\Block\HeadElementTracker;
use Magento\Framework\View\Element\AbstractBlock;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class HeadElementTrackerTest extends TestCase
{
    private $headTagManager;
    private $elementSerializer;
    private $logger;
    private $block1;
    private $block2;
    private $headElementTracker;

    protected function setUp(): void
    {
        $this->headTagManager = $this->createMock(HeadTagManager::class);
        $this->elementSerializer = $this->createMock(HeadElementSerializerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->block1 = $this->createMock(AbstractBlock::class);
        $this->block1->method('getNameInLayout')->willReturn('block1');

        $this->block2 = $this->createMock(AbstractBlock::class);
        $this->block2->method('getNameInLayout')->willReturn('block2');

        $this->headElementTracker = new HeadElementTracker(
            $this->headTagManager,
            $this->elementSerializer,
            $this->logger
        );
    }

    public function testStartTrackingWithSingleBlock()
    {
        $this->headTagManager->expects($this->once())
            ->method('getAllElements')
            ->willReturn(['existing1' => $this->createMock(HeadElementInterface::class)]);

        $this->headElementTracker->startTracking($this->block1);

        $this->assertEquals('block1', $this->headElementTracker->getCurrentTrackingBlock());
        $this->assertTrue($this->headElementTracker->isBlockBeingTracked($this->block1));
        $this->assertEquals(1, $this->headElementTracker->getTrackingLevel());
    }

    public function testStartTrackingWithNestedBlocks()
    {
        $this->headTagManager->method('getAllElements')
            ->willReturnOnConsecutiveCalls(
                ['existing1' => $this->createMock(HeadElementInterface::class)],
                ['existing1' => $this->createMock(HeadElementInterface::class), 'new1' => $this->createMock(HeadElementInterface::class)]
            );

        // Start tracking first block
        $this->headElementTracker->startTracking($this->block1);
        $this->assertEquals(1, $this->headElementTracker->getTrackingLevel());

        // Start tracking nested block
        $this->headElementTracker->startTracking($this->block2);
        $this->assertEquals(2, $this->headElementTracker->getTrackingLevel());

        // Both blocks should be tracked
        $this->assertTrue($this->headElementTracker->isBlockBeingTracked($this->block1));
        $this->assertTrue($this->headElementTracker->isBlockBeingTracked($this->block2));

        // Current tracking block should be the most recent one
        $this->assertEquals('block2', $this->headElementTracker->getCurrentTrackingBlock());
    }

    public function testStartTrackingSkipsBlockWithoutName()
    {
        $blockWithoutName = $this->createMock(AbstractBlock::class);
        $blockWithoutName->method('getNameInLayout')->willReturn(null);

        $this->headTagManager->expects($this->never())
            ->method('getAllElements');

        $this->headElementTracker->startTracking($blockWithoutName);

        $this->assertNull($this->headElementTracker->getCurrentTrackingBlock());
        $this->assertEquals(0, $this->headElementTracker->getTrackingLevel());
    }

    public function testStopTrackingAndGetNewElements()
    {
        $element1 = $this->createMock(HeadElementInterface::class);
        $element2 = $this->createMock(HeadElementInterface::class);
        $newElement = $this->createMock(HeadElementInterface::class);

        $this->headTagManager->method('getAllElements')
            ->willReturnOnConsecutiveCalls(
                ['existing1' => $element1], // Initial elements
                ['existing1' => $element1, 'new1' => $newElement] // Elements after rendering
            );

        $this->elementSerializer->expects($this->once())
            ->method('serialize')
            ->with(['new1' => $newElement])
            ->willReturn(['serialized_new1' => 'serialized_data']);

        // Start tracking
        $this->headElementTracker->startTracking($this->block1);

        // Stop tracking and get new elements
        $newElements = $this->headElementTracker->stopTrackingAndGetNewElements($this->block1);

        $this->assertEquals(['serialized_new1' => 'serialized_data'], $newElements);
    }

    public function testStopTrackingWithoutNewElements()
    {
        $element1 = $this->createMock(HeadElementInterface::class);

        $this->headTagManager->method('getAllElements')
            ->willReturnOnConsecutiveCalls(
                ['existing1' => $element1], // Initial elements
                ['existing1' => $element1] // Same elements after rendering
            );

        $this->elementSerializer->expects($this->never())
            ->method('serialize');

        // Start tracking
        $this->headElementTracker->startTracking($this->block1);

        // Stop tracking and get new elements
        $newElements = $this->headElementTracker->stopTrackingAndGetNewElements($this->block1);

        $this->assertEquals([], $newElements);
    }

    public function testStopTrackingNonExistentBlock()
    {
        $nonExistentBlock = $this->createMock(AbstractBlock::class);
        $nonExistentBlock->method('getNameInLayout')->willReturn('non_existent');

        $newElements = $this->headElementTracker->stopTrackingAndGetNewElements($nonExistentBlock);

        $this->assertEquals([], $newElements);
    }

    public function testClearTrackingReducesLevel()
    {
        $this->headTagManager->method('getAllElements')
            ->willReturnOnConsecutiveCalls(
                ['existing1' => $this->createMock(HeadElementInterface::class)],
                ['existing1' => $this->createMock(HeadElementInterface::class)]
            );

        // Start tracking two blocks
        $this->headElementTracker->startTracking($this->block1);
        $this->headElementTracker->startTracking($this->block2);
        $this->assertEquals(2, $this->headElementTracker->getTrackingLevel());

        // Clear one block
        $this->headElementTracker->clearTracking($this->block2);
        $this->assertEquals(1, $this->headElementTracker->getTrackingLevel());
        $this->assertFalse($this->headElementTracker->isBlockBeingTracked($this->block2));
        $this->assertTrue($this->headElementTracker->isBlockBeingTracked($this->block1));

        // Clear the other block
        $this->headElementTracker->clearTracking($this->block1);
        $this->assertEquals(0, $this->headElementTracker->getTrackingLevel());
        $this->assertFalse($this->headElementTracker->isBlockBeingTracked($this->block1));
    }

    public function testClearTrackingNonExistentBlock()
    {
        $this->headTagManager->method('getAllElements')
            ->willReturn(['existing1' => $this->createMock(HeadElementInterface::class)]);

        // Start tracking
        $this->headElementTracker->startTracking($this->block1);
        $this->assertEquals(1, $this->headElementTracker->getTrackingLevel());

        $nonExistentBlock = $this->createMock(AbstractBlock::class);
        $nonExistentBlock->method('getNameInLayout')->willReturn('non_existent');

        // Clear non-existent block should not affect tracking level
        $this->headElementTracker->clearTracking($nonExistentBlock);
        $this->assertEquals(1, $this->headElementTracker->getTrackingLevel());
    }

    public function testIsBlockBeingTrackedWithoutTracking()
    {
        $this->assertFalse($this->headElementTracker->isBlockBeingTracked($this->block1));
    }

    public function testGetCurrentTrackingBlockReturnsNull()
    {
        $this->assertNull($this->headElementTracker->getCurrentTrackingBlock());
    }

    public function testNestedTrackingOrder()
    {
        $element1 = $this->createMock(HeadElementInterface::class);
        $element2 = $this->createMock(HeadElementInterface::class);
        $element3 = $this->createMock(HeadElementInterface::class);

        $this->headTagManager->method('getAllElements')
            ->willReturnOnConsecutiveCalls(
                ['existing1' => $element1], // Initial state
                ['existing1' => $element1, 'new1' => $element2], // After parent starts
                ['existing1' => $element1, 'new1' => $element2, 'new2' => $element3] // After child renders
            );

        // Start tracking parent block
        $this->headElementTracker->startTracking($this->block1);

        // Start tracking child block
        $this->headElementTracker->startTracking($this->block2);

        // Current tracking should be child
        $this->assertEquals('block2', $this->headElementTracker->getCurrentTrackingBlock());

        // Stop tracking child block first (LIFO) - it should find new2 element
        $this->elementSerializer->expects($this->once())
            ->method('serialize')
            ->with(['new2' => $element3])
            ->willReturn(['serialized_new2' => 'data']);

        $newElements = $this->headElementTracker->stopTrackingAndGetNewElements($this->block2);
        $this->assertEquals(['serialized_new2' => 'data'], $newElements);

        // After clearing child, parent should be current
        $this->headElementTracker->clearTracking($this->block2);
        $this->assertEquals('block1', $this->headElementTracker->getCurrentTrackingBlock());
        $this->assertEquals(1, $this->headElementTracker->getTrackingLevel());
    }
}