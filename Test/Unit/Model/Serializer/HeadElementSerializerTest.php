<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\Serializer;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementFactoryInterface;
use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Api\Registry\HeadElementFactoryRegistryInterface;
use Hryvinskyi\HeadTagManager\Api\Serializer\Strategy\HeadElementSerializationStrategyInterface;
use Hryvinskyi\HeadTagManager\Api\Serializer\Strategy\SerializationStrategyRegistryInterface;
use Hryvinskyi\HeadTagManager\Model\Serializer\HeadElementSerializer;
use Hryvinskyi\HeadTagManager\Model\Serializer\StorableAttributes;
use Hryvinskyi\HeadTagManager\Test\Unit\Model\Serializer\Stub\InterceptedCustomElement;
use Hryvinskyi\HeadTagManager\Test\Unit\Model\Serializer\Stub\InterceptedCustomElement\Interceptor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class HeadElementSerializerTest extends TestCase
{
    /** @var HeadElementFactoryRegistryInterface&MockObject */
    private HeadElementFactoryRegistryInterface $factoryRegistry;

    /** @var SerializationStrategyRegistryInterface&MockObject */
    private SerializationStrategyRegistryInterface $strategyRegistry;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    /** @var HeadElementSerializer */
    private HeadElementSerializer $serializer;

    protected function setUp(): void
    {
        $this->factoryRegistry = $this->createMock(HeadElementFactoryRegistryInterface::class);
        $this->strategyRegistry = $this->createMock(SerializationStrategyRegistryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->serializer = new HeadElementSerializer(
            $this->factoryRegistry,
            $this->strategyRegistry,
            $this->logger,
            new StorableAttributes()
        );
    }

    public function testSerializeWithStrategy(): void
    {
        $element = $this->createMock(HeadElementInterface::class);
        $strategy = $this->createMock(HeadElementSerializationStrategyInterface::class);

        $expectedData = [
            'type' => 'TestElement',
            'short_type' => 'test',
            'attributes' => ['name' => 'test'],
            'content' => null
        ];

        $this->strategyRegistry->expects($this->once())
            ->method('getStrategyForElement')
            ->with($element)
            ->willReturn($strategy);

        $strategy->expects($this->once())
            ->method('serialize')
            ->with($element, '0')
            ->willReturn($expectedData);

        $result = $this->serializer->serialize([$element]);

        $this->assertEquals([0 => $expectedData], $result);
    }

    public function testSerializeLeavesOutATypeItsShortTypeAlreadyResolves(): void
    {
        $element = new Interceptor();
        $strategy = $this->createMock(HeadElementSerializationStrategyInterface::class);
        $factory = $this->createMock(HeadElementFactoryInterface::class);
        $this->strategyRegistry->method('getStrategyForElement')->willReturn($strategy);
        $strategy->method('serialize')->willReturn([
            'type' => Interceptor::class,
            'short_type' => 'test',
            'attributes' => ['name' => 'test'],
            'sort_order' => 5,
        ]);
        $this->factoryRegistry->method('getFactoryByClassName')
            ->willReturnMap([[InterceptedCustomElement::class, $factory]]);
        $this->factoryRegistry->method('getFactoryByType')->willReturnMap([['test', $factory]]);

        $result = $this->serializer->serialize(['el' => $element]);

        $this->assertSame(
            ['el' => ['short_type' => 'test', 'attributes' => ['name' => 'test'], 'sort_order' => 5]],
            $result
        );
    }

    public function testSerializeKeepsATypeThatResolvesToAnotherFactoryThanItsShortType(): void
    {
        $element = $this->createMock(HeadElementInterface::class);
        $strategy = $this->createMock(HeadElementSerializationStrategyInterface::class);
        $data = ['type' => 'Vendor\\Custom\\Link', 'short_type' => 'link', 'attributes' => [], 'sort_order' => 1];
        $this->strategyRegistry->method('getStrategyForElement')->willReturn($strategy);
        $strategy->method('serialize')->willReturn($data);
        $this->factoryRegistry->method('getFactoryByClassName')
            ->willReturn($this->createMock(HeadElementFactoryInterface::class));
        $this->factoryRegistry->method('getFactoryByType')
            ->willReturn($this->createMock(HeadElementFactoryInterface::class));

        $this->assertSame(['el' => $data], $this->serializer->serialize(['el' => $element]));
    }

    public function testSerializeWithoutStrategy(): void
    {
        // Create a test element that implements getAttributes and getSortOrder
        $element = new class implements HeadElementInterface {
            /**
             * @inheritDoc
             */
            public function render(): string
            {
                return '<test>';
            }

            /**
             * @inheritDoc
             */
            public function getAttributes(): array
            {
                return ['name' => 'test'];
            }

            /**
             * @inheritDoc
             */
            public function getSortOrder(): int
            {
                return 100;
            }
        };

        $this->strategyRegistry->expects($this->once())
            ->method('getStrategyForElement')
            ->with($element)
            ->willReturn(null);

        $this->factoryRegistry->expects($this->once())
            ->method('getElementTypeByClassName')
            ->with(get_class($element))
            ->willReturn('unknown');

        $result = $this->serializer->serialize([$element]);

        $expected = [
            0 => [
                'type' => get_class($element),
                'short_type' => 'unknown',
                'attributes' => ['name' => 'test'],
                'sort_order' => 100,
                'content' => null
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    public function testSerializeWithoutStrategyResolvesTheTypeOfAnInterceptedElementThroughItsParent(): void
    {
        $element = new Interceptor();

        $this->strategyRegistry->method('getStrategyForElement')->willReturn(null);
        $this->factoryRegistry->method('getElementTypeByClassName')
            ->willReturnCallback(
                static fn (string $className): ?string => $className === InterceptedCustomElement::class
                    ? 'custom'
                    : null
            );

        $result = $this->serializer->serialize([$element]);

        $this->assertSame(InterceptedCustomElement::class, $result[0]['type'] ?? null);
        $this->assertSame('custom', $result[0]['short_type'] ?? null);
    }

    public function testSerializeHandlesExceptions(): void
    {
        $element = $this->createMock(HeadElementInterface::class);

        $this->strategyRegistry->expects($this->once())
            ->method('getStrategyForElement')
            ->with($element)
            ->willThrowException(new \Exception('Test exception'));

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Failed to serialize head element',
                $this->callback(static function (array $context) use ($element): bool {
                    return isset($context['key']) && $context['key'] === 0
                        && isset($context['element_class']) && $context['element_class'] === get_class($element)
                        && isset($context['exception']) && $context['exception'] === 'Test exception';
                })
            );

        $result = $this->serializer->serialize([$element]);

        $this->assertEquals([], $result);
    }

    public function testUnserializeWithFactory(): void
    {
        $elementData = [
            'type' => 'TestElement',
            'attributes' => ['name' => 'test'],
            'content' => 'test content',
            'sort_order' => 50
        ];

        $factory = $this->createMock(HeadElementFactoryInterface::class);
        $element = $this->createMock(HeadElementInterface::class);

        $this->factoryRegistry->expects($this->once())
            ->method('getFactoryByClassName')
            ->with('TestElement')
            ->willReturn($factory);

        $factory->expects($this->once())
            ->method('create')
            ->with(['attributes' => ['name' => 'test'], 'content' => 'test content', 'sort_order' => 50])
            ->willReturn($element);

        $result = $this->serializer->unserialize(['key1' => $elementData]);

        $this->assertEquals(['key1' => $element], $result);
    }

    public function testUnserializeKeepsAnArrayAttributeValue(): void
    {
        $factory = $this->createMock(HeadElementFactoryInterface::class);
        $element = $this->createMock(HeadElementInterface::class);

        $this->factoryRegistry->method('getFactoryByClassName')->willReturn($factory);
        $factory->expects($this->once())
            ->method('create')
            ->with(['attributes' => ['rel' => 'preload', 'data' => ['variant' => 'hero']]])
            ->willReturn($element);

        $result = $this->serializer->unserialize([
            'key1' => ['type' => 'TestElement', 'attributes' => ['rel' => 'preload', 'data' => ['variant' => 'hero']]],
        ]);

        $this->assertSame(['key1' => $element], $result);
    }

    public function testUnserializeWithShortTypeOnly(): void
    {
        $elementData = [
            'short_type' => 'meta',
            'attributes' => ['name' => 'test']
        ];

        $factory = $this->createMock(HeadElementFactoryInterface::class);
        $element = $this->createMock(HeadElementInterface::class);

        $this->factoryRegistry->expects($this->never())->method('getFactoryByClassName');
        $this->factoryRegistry->expects($this->once())
            ->method('getFactoryByType')
            ->with('meta')
            ->willReturn($factory);

        $factory->expects($this->once())
            ->method('create')
            ->with(['attributes' => ['name' => 'test']])
            ->willReturn($element);

        $result = $this->serializer->unserialize(['key1' => $elementData]);

        $this->assertSame(['key1' => $element], $result);
    }

    public function testUnserializeRestoresAnUnregisteredSubclassThroughItsShortType(): void
    {
        $element = $this->createMock(HeadElementInterface::class);
        $factory = $this->createMock(HeadElementFactoryInterface::class);
        $factory->expects($this->once())
            ->method('create')
            ->with(['attributes' => ['name' => 'robots'], 'sort_order' => 20])
            ->willReturn($element);

        $this->factoryRegistry->expects($this->once())
            ->method('getFactoryByClassName')
            ->with('Vendor\\X\\MetaElement')
            ->willReturn(null);
        $this->factoryRegistry->expects($this->once())
            ->method('getFactoryByType')
            ->with('meta')
            ->willReturn($factory);
        $this->logger->expects($this->never())->method('warning');

        $result = $this->serializer->unserialize([
            'key' => [
                'type' => 'Vendor\\X\\MetaElement',
                'short_type' => 'meta',
                'attributes' => ['name' => 'robots'],
                'sort_order' => 20,
            ],
        ]);

        $this->assertSame(['key' => $element], $result);
    }

    public function testUnserializePrefersTheClassFactoryOverTheShortType(): void
    {
        $element = $this->createMock(HeadElementInterface::class);
        $factory = $this->createMock(HeadElementFactoryInterface::class);
        $factory->method('create')->willReturn($element);

        $this->factoryRegistry->method('getFactoryByClassName')->with('Vendor\\X\\MetaElement')->willReturn($factory);
        $this->factoryRegistry->expects($this->never())->method('getFactoryByType');

        $result = $this->serializer->unserialize([
            'key' => ['type' => 'Vendor\\X\\MetaElement', 'short_type' => 'meta', 'attributes' => []],
        ]);

        $this->assertSame(['key' => $element], $result);
    }

    public function testUnserializeLeavesTheSortOrderToTheFactoryWhenTheDataHasNone(): void
    {
        $element = $this->createMock(HeadElementInterface::class);
        $factory = $this->createMock(HeadElementFactoryInterface::class);
        $factory->expects($this->once())
            ->method('create')
            ->with($this->callback(
                static fn (array $data): bool => !array_key_exists('sort_order', $data)
                    && $data === ['attributes' => ['rel' => 'x'], 'content' => '.a{}']
            ))
            ->willReturn($element);
        $this->factoryRegistry->method('getFactoryByClassName')->willReturn($factory);

        $result = $this->serializer->unserialize([
            'key' => ['type' => 'Vendor\\X\\StyleElement', 'attributes' => ['rel' => 'x'], 'content' => '.a{}'],
        ]);

        $this->assertSame(['key' => $element], $result);
    }

    public function testUnserializeReadsAnInterceptorAsTheClassItWraps(): void
    {
        $element = $this->createMock(HeadElementInterface::class);
        $factory = $this->createMock(HeadElementFactoryInterface::class);
        $factory->method('create')->willReturn($element);

        $this->factoryRegistry->expects($this->once())
            ->method('getFactoryByClassName')
            ->with('Vendor\\Module\\CustomElement')
            ->willReturn($factory);

        $result = $this->serializer->unserialize([
            'key' => ['type' => 'Vendor\\Module\\CustomElement\\Interceptor', 'attributes' => []],
        ]);

        $this->assertSame(['key' => $element], $result);
    }

    public function testUnserializeWithNoFactoryLogsTheEntryThatCannotBeRecreated(): void
    {
        $this->factoryRegistry->expects($this->once())
            ->method('getFactoryByClassName')
            ->with('UnknownElement')
            ->willReturn(null);
        $this->factoryRegistry->expects($this->once())
            ->method('getFactoryByType')
            ->with('unknown')
            ->willReturn(null);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Head element cannot be recreated: no factory is registered for it',
                $this->callback(static fn (array $context): bool => $context['key'] === 'key1'
                    && $context['type'] === 'UnknownElement'
                    && $context['short_type'] === 'unknown')
            );

        $result = $this->serializer->unserialize([
            'key1' => ['type' => 'UnknownElement', 'short_type' => 'unknown', 'attributes' => ['name' => 'test']],
        ]);

        $this->assertSame([], $result);
    }

    public function testUnserializeLogsAnEntryWithoutAnyType(): void
    {
        $this->factoryRegistry->expects($this->never())->method('getFactoryByClassName');
        $this->factoryRegistry->expects($this->never())->method('getFactoryByType');
        $this->logger->expects($this->once())->method('warning');

        $this->assertSame([], $this->serializer->unserialize(['key1' => ['attributes' => ['name' => 'test']]]));
    }

    public function testUnserializeLogsAnEntryThatIsNotAnArray(): void
    {
        $this->logger->expects($this->once())->method('warning');

        $this->assertSame([], $this->serializer->unserialize(['key1' => 'broken']));
    }

    public function testUnserializeHandlesExceptions(): void
    {
        $elementData = [
            'type' => 'TestElement',
            'attributes' => ['name' => 'test']
        ];

        $this->factoryRegistry->expects($this->once())
            ->method('getFactoryByClassName')
            ->with('TestElement')
            ->willThrowException(new \Exception('Test exception'));

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Failed to unserialize head element',
                $this->callback(static function (array $context) use ($elementData): bool {
                    return isset($context['key']) && $context['key'] === 'key1'
                        && isset($context['element_data']) && $context['element_data'] === $elementData
                        && isset($context['exception']) && $context['exception'] === 'Test exception';
                })
            );

        $result = $this->serializer->unserialize(['key1' => $elementData]);

        $this->assertEquals([], $result);
    }
}
