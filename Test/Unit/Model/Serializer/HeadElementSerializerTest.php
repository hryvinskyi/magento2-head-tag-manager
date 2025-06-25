<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\Serializer;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Api\Registry\HeadElementFactoryRegistryInterface;
use Hryvinskyi\HeadTagManager\Api\Serializer\Strategy\HeadElementSerializationStrategyInterface;
use Hryvinskyi\HeadTagManager\Api\Serializer\Strategy\SerializationStrategyRegistryInterface;
use Hryvinskyi\HeadTagManager\Model\Serializer\HeadElementSerializer;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class HeadElementSerializerTest extends TestCase
{
    private HeadElementFactoryRegistryInterface $factoryRegistry;
    private SerializationStrategyRegistryInterface $strategyRegistry;
    private LoggerInterface $logger;
    private HeadElementSerializer $serializer;

    protected function setUp(): void
    {
        $this->factoryRegistry = $this->createMock(HeadElementFactoryRegistryInterface::class);
        $this->strategyRegistry = $this->createMock(SerializationStrategyRegistryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->serializer = new HeadElementSerializer(
            $this->factoryRegistry,
            $this->strategyRegistry,
            $this->logger
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

    public function testSerializeWithoutStrategy(): void
    {
        // Create a test element that implements getAttributes
        $element = new class implements HeadElementInterface {
            public function render(): string { return '<test>'; }
            public function getAttributes(): array { return ['name' => 'test']; }
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
                'content' => null
            ]
        ];

        $this->assertEquals($expected, $result);
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
                $this->callback(function ($context) use ($element) {
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
            'content' => 'test content'
        ];

        $factory = $this->createMock(\Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementFactoryInterface::class);
        $element = $this->createMock(HeadElementInterface::class);

        $this->factoryRegistry->expects($this->once())
            ->method('getFactoryByClassName')
            ->with('TestElement')
            ->willReturn($factory);

        $factory->expects($this->once())
            ->method('create')
            ->with(['attributes' => ['name' => 'test'], 'content' => 'test content'])
            ->willReturn($element);

        $result = $this->serializer->unserialize(['key1' => $elementData]);

        $this->assertEquals(['key1' => $element], $result);
    }

    public function testUnserializeWithShortType(): void
    {
        $elementData = [
            'short_type' => 'meta',
            'attributes' => ['name' => 'test']
        ];

        $factory = $this->createMock(\Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementFactoryInterface::class);
        $element = $this->createMock(HeadElementInterface::class);

        $this->factoryRegistry->expects($this->once())
            ->method('getFactoryByClassName')
            ->with('meta')
            ->willReturn(null);

        $this->factoryRegistry->expects($this->once())
            ->method('getFactoryByType')
            ->with('meta')
            ->willReturn($factory);

        $factory->expects($this->once())
            ->method('create')
            ->with(['attributes' => ['name' => 'test']])
            ->willReturn($element);

        $result = $this->serializer->unserialize(['key1' => $elementData]);

        $this->assertEquals(['key1' => $element], $result);
    }

    public function testUnserializeWithNoFactory(): void
    {
        $elementData = [
            'type' => 'UnknownElement',
            'attributes' => ['name' => 'test']
        ];

        $this->factoryRegistry->expects($this->once())
            ->method('getFactoryByClassName')
            ->with('UnknownElement')
            ->willReturn(null);

        $this->factoryRegistry->expects($this->once())
            ->method('getFactoryByType')
            ->with('UnknownElement')
            ->willReturn(null);

        $result = $this->serializer->unserialize(['key1' => $elementData]);

        $this->assertEquals([], $result);
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
                $this->callback(function ($context) use ($elementData) {
                    return isset($context['key']) && $context['key'] === 'key1'
                        && isset($context['element_data']) && $context['element_data'] === $elementData
                        && isset($context['exception']) && $context['exception'] === 'Test exception';
                })
            );

        $result = $this->serializer->unserialize(['key1' => $elementData]);

        $this->assertEquals([], $result);
    }

    public function testUnserializeWithEmptyType(): void
    {
        $elementData = [
            'attributes' => ['name' => 'test']
        ];

        $result = $this->serializer->unserialize(['key1' => $elementData]);

        $this->assertEquals([], $result);
    }
}