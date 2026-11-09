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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SerializationStrategyRegistryTest extends TestCase
{
    /** @var SerializationStrategyRegistry */
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

        // First call should invoke canHandle; the second one uses the cached result
        $first = $this->registry->getStrategyForElement($element);
        $second = $this->registry->getStrategyForElement($element);

        $this->assertSame($strategy, $first);
        $this->assertSame($first, $second);
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

    /**
     * @return array<string, array{list<int>}> the priorities in registration order
     */
    public static function competingPriorities(): array
    {
        return [
            'higher first' => [[200, 100]],
            'higher last' => [[100, 200]],
        ];
    }

    /**
     * @param list<int> $priorities
     * @dataProvider competingPriorities
     */
    public function testHigherPriorityWinsTheExactClassAndTypeLookups(array $priorities): void
    {
        $strategies = [];
        foreach ($priorities as $priority) {
            $strategies[$priority] = $this->strategyFor('MetaElement', 'meta', $priority);
        }

        $registry = new SerializationStrategyRegistry(array_values($strategies));

        $this->assertSame($strategies[200], $registry->getStrategyByClassName('MetaElement'));
        $this->assertSame($strategies[200], $registry->getStrategyByType('meta'));
    }

    /**
     * @param list<int> $priorities
     * @dataProvider competingPriorities
     */
    public function testHigherPriorityWinsWhenRegisteredAtRuntime(array $priorities): void
    {
        $strategies = [];
        foreach ($priorities as $priority) {
            $strategies[$priority] = $this->strategyFor('MetaElement', 'meta', $priority);
            $this->registry->registerStrategy($strategies[$priority]);
        }

        $this->assertSame($strategies[200], $this->registry->getStrategyByClassName('MetaElement'));
        $this->assertSame($strategies[200], $this->registry->getStrategyByType('meta'));
        $this->assertSame([$strategies[200], $strategies[100]], $this->registry->getAllStrategies());
    }

    public function testEqualPriorityLetsTheLaterRegisteredWin(): void
    {
        $first = $this->strategyFor('MetaElement', 'meta', 100);
        $second = $this->strategyFor('MetaElement', 'meta', 100);

        $registry = new SerializationStrategyRegistry([$first, $second]);

        $this->assertSame($second, $registry->getStrategyByClassName('MetaElement'));
        $this->assertSame($second, $registry->getStrategyByType('meta'));
    }

    public function testCustomStrategyWithTheDefaultPriorityReplacesTheBuiltInOneAtRuntime(): void
    {
        $builtIn = $this->strategyFor('MetaElement', 'meta', 100);
        $custom = $this->strategyFor('MetaElement', 'meta', 100);
        $this->registry->registerStrategy($builtIn);

        $this->registry->registerStrategy($custom);

        $this->assertSame($custom, $this->registry->getStrategyByClassName('MetaElement'));
        $this->assertSame($custom, $this->registry->getStrategyByType('meta'));
    }

    public function testLowerPriorityRegisteredLaterDoesNotReplaceTheStoredStrategy(): void
    {
        $builtIn = $this->strategyFor('MetaElement', 'meta', 100);
        $lower = $this->strategyFor('MetaElement', 'meta', 50);

        $this->registry->registerStrategy($builtIn);
        $this->registry->registerStrategy($lower);

        $this->assertSame($builtIn, $this->registry->getStrategyByClassName('MetaElement'));
        $this->assertSame($builtIn, $this->registry->getStrategyByType('meta'));
    }

    public function testEqualPriorityLetsTheLaterRegisteredWinTheCanHandleFallback(): void
    {
        $element = $this->createMock(HeadElementInterface::class);
        $first = $this->strategyFor('FirstElement', 'first', 100, true);
        $second = $this->strategyFor('SecondElement', 'second', 100, true);

        $this->registry->registerStrategy($first);
        $this->registry->registerStrategy($second);

        $this->assertSame($second, $this->registry->getStrategyForElement($element));
        $this->assertSame([$second, $first], $this->registry->getAllStrategies());
    }

    public function testStrategyRegisteredLaterIsConsideredByTheCanHandleFallback(): void
    {
        $element = $this->createMock(HeadElementInterface::class);
        $low = $this->strategyFor('LowElement', 'low', 100, true);
        $high = $this->strategyFor('HighElement', 'high', 200, true);

        $this->registry->registerStrategy($low);
        $this->assertSame($low, $this->registry->getStrategyForElement($element));

        $this->registry->registerStrategy($high);
        $this->assertSame($high, $this->registry->getStrategyForElement($element));
    }

    /**
     * Build a strategy double
     *
     * @param string $className the element class it handles
     * @param string $type the element type it handles
     * @param int $priority its priority
     * @param bool $canHandle what canHandle() answers
     * @return HeadElementSerializationStrategyInterface&MockObject
     */
    private function strategyFor(
        string $className,
        string $type,
        int $priority,
        bool $canHandle = false
    ): HeadElementSerializationStrategyInterface {
        $strategy = $this->createMock(HeadElementSerializationStrategyInterface::class);
        $strategy->method('getElementType')->willReturn($type);
        $strategy->method('getElementClassName')->willReturn($className);
        $strategy->method('getPriority')->willReturn($priority);
        $strategy->method('canHandle')->willReturn($canHandle);

        return $strategy;
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
