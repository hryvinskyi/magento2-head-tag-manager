<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\Serializer\Strategy;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Api\Serializer\Strategy\HeadElementSerializationStrategyInterface;
use Hryvinskyi\HeadTagManager\Model\Serializer\Strategy\SerializationStrategyRegistry;
use PHPUnit\Framework\TestCase;

class SerializationStrategyRegistryTest extends TestCase
{
    private SerializationStrategyRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new SerializationStrategyRegistry();
    }

    public function testRegisterAndGetStrategyByType(): void
    {
        $strategy = $this->createMock(HeadElementSerializationStrategyInterface::class);
        $strategy->method('getElementType')->willReturn('meta');
        $strategy->method('getElementClassName')->willReturn('MetaElement');

        $this->registry->registerStrategy($strategy);

        $this->assertSame($strategy, $this->registry->getStrategyByType('meta'));
    }

    public function testRegisterAndGetStrategyByClassName(): void
    {
        $strategy = $this->createMock(HeadElementSerializationStrategyInterface::class);
        $strategy->method('getElementType')->willReturn('meta');
        $strategy->method('getElementClassName')->willReturn('MetaElement');

        $this->registry->registerStrategy($strategy);

        $this->assertSame($strategy, $this->registry->getStrategyByClassName('MetaElement'));
    }

    public function testGetStrategyForElement(): void
    {
        $element = $this->createMock(HeadElementInterface::class);
        $strategy = $this->createMock(HeadElementSerializationStrategyInterface::class);
        $strategy->method('getElementType')->willReturn('meta');
        $strategy->method('getElementClassName')->willReturn(get_class($element));
        $strategy->method('canHandle')->with($element)->willReturn(true);

        $this->registry->registerStrategy($strategy);

        $this->assertSame($strategy, $this->registry->getStrategyForElement($element));
    }

    public function testGetStrategyForElementByClassNameFirst(): void
    {
        $element = $this->createMock(HeadElementInterface::class);
        $strategy = $this->createMock(HeadElementSerializationStrategyInterface::class);
        $strategy->method('getElementType')->willReturn('meta');
        $strategy->method('getElementClassName')->willReturn(get_class($element));

        $this->registry->registerStrategy($strategy);

        // Should find by class name without calling canHandle
        $strategy->expects($this->never())->method('canHandle');

        $this->assertSame($strategy, $this->registry->getStrategyForElement($element));
    }

    public function testGetStrategyForElementFallsBackToCanHandle(): void
    {
        $element = $this->createMock(HeadElementInterface::class);
        $strategy = $this->createMock(HeadElementSerializationStrategyInterface::class);
        $strategy->method('getElementType')->willReturn('meta');
        $strategy->method('getElementClassName')->willReturn('DifferentElement');
        $strategy->method('canHandle')->with($element)->willReturn(true);

        $this->registry->registerStrategy($strategy);

        $this->assertSame($strategy, $this->registry->getStrategyForElement($element));
    }

    public function testGetStrategyForElementCachesResult(): void
    {
        $element = $this->createMock(HeadElementInterface::class);
        $strategy = $this->createMock(HeadElementSerializationStrategyInterface::class);
        $strategy->method('getElementType')->willReturn('meta');
        $strategy->method('getElementClassName')->willReturn('DifferentElement');
        $strategy->expects($this->once())->method('canHandle')->with($element)->willReturn(true);

        $this->registry->registerStrategy($strategy);

        // First call should invoke canHandle
        $this->assertSame($strategy, $this->registry->getStrategyForElement($element));
        
        // Second call should use cached result
        $this->assertSame($strategy, $this->registry->getStrategyForElement($element));
    }

    public function testGetAllStrategies(): void
    {
        $strategy1 = $this->createMock(HeadElementSerializationStrategyInterface::class);
        $strategy1->method('getElementType')->willReturn('meta');
        $strategy1->method('getElementClassName')->willReturn('MetaElement');

        $strategy2 = $this->createMock(HeadElementSerializationStrategyInterface::class);
        $strategy2->method('getElementType')->willReturn('link');
        $strategy2->method('getElementClassName')->willReturn('LinkElement');

        $this->registry->registerStrategy($strategy1);
        $this->registry->registerStrategy($strategy2);

        $strategies = $this->registry->getAllStrategies();

        $this->assertCount(2, $strategies);
        $this->assertContains($strategy1, $strategies);
        $this->assertContains($strategy2, $strategies);
    }

    public function testHasStrategyForType(): void
    {
        $strategy = $this->createMock(HeadElementSerializationStrategyInterface::class);
        $strategy->method('getElementType')->willReturn('meta');
        $strategy->method('getElementClassName')->willReturn('MetaElement');

        $this->assertFalse($this->registry->hasStrategyForType('meta'));

        $this->registry->registerStrategy($strategy);

        $this->assertTrue($this->registry->hasStrategyForType('meta'));
        $this->assertFalse($this->registry->hasStrategyForType('link'));
    }

    public function testClear(): void
    {
        $strategy = $this->createMock(HeadElementSerializationStrategyInterface::class);
        $strategy->method('getElementType')->willReturn('meta');
        $strategy->method('getElementClassName')->willReturn('MetaElement');

        $this->registry->registerStrategy($strategy);
        $this->assertTrue($this->registry->hasStrategyForType('meta'));

        $this->registry->clear();

        $this->assertFalse($this->registry->hasStrategyForType('meta'));
        $this->assertEmpty($this->registry->getAllStrategies());
    }

    public function testConstructorWithStrategies(): void
    {
        $strategy1 = $this->createMock(HeadElementSerializationStrategyInterface::class);
        $strategy1->method('getElementType')->willReturn('meta');
        $strategy1->method('getElementClassName')->willReturn('MetaElement');
        $strategy1->method('getPriority')->willReturn(100);

        $strategy2 = $this->createMock(HeadElementSerializationStrategyInterface::class);
        $strategy2->method('getElementType')->willReturn('link');
        $strategy2->method('getElementClassName')->willReturn('LinkElement');
        $strategy2->method('getPriority')->willReturn(200);

        $registry = new SerializationStrategyRegistry([$strategy1, $strategy2]);

        $this->assertTrue($registry->hasStrategyForType('meta'));
        $this->assertTrue($registry->hasStrategyForType('link'));
        $this->assertSame($strategy1, $registry->getStrategyByType('meta'));
        $this->assertSame($strategy2, $registry->getStrategyByType('link'));
    }

    public function testStrategiesSortedByPriority(): void
    {
        $lowPriorityStrategy = $this->createMock(HeadElementSerializationStrategyInterface::class);
        $lowPriorityStrategy->method('getElementType')->willReturn('meta');
        $lowPriorityStrategy->method('getElementClassName')->willReturn('MetaElement');
        $lowPriorityStrategy->method('getPriority')->willReturn(50);

        $highPriorityStrategy = $this->createMock(HeadElementSerializationStrategyInterface::class);
        $highPriorityStrategy->method('getElementType')->willReturn('link');
        $highPriorityStrategy->method('getElementClassName')->willReturn('LinkElement');
        $highPriorityStrategy->method('getPriority')->willReturn(200);

        // Pass in low priority first, but it should be sorted by priority
        $registry = new SerializationStrategyRegistry([$lowPriorityStrategy, $highPriorityStrategy]);
        $strategies = $registry->getAllStrategies();

        // High priority strategy should come first
        $this->assertSame($highPriorityStrategy, $strategies[0]);
        $this->assertSame($lowPriorityStrategy, $strategies[1]);
    }

    public function testGetStrategyByTypeReturnsNullForUnknownType(): void
    {
        $this->assertNull($this->registry->getStrategyByType('unknown'));
    }

    public function testGetStrategyByClassNameReturnsNullForUnknownClass(): void
    {
        $this->assertNull($this->registry->getStrategyByClassName('UnknownElement'));
    }

    public function testGetStrategyForElementReturnsNullWhenNoStrategyCanHandle(): void
    {
        $element = $this->createMock(HeadElementInterface::class);
        $strategy = $this->createMock(HeadElementSerializationStrategyInterface::class);
        $strategy->method('getElementType')->willReturn('meta');
        $strategy->method('getElementClassName')->willReturn('DifferentElement');
        $strategy->method('canHandle')->with($element)->willReturn(false);

        $this->registry->registerStrategy($strategy);

        $this->assertNull($this->registry->getStrategyForElement($element));
    }
}