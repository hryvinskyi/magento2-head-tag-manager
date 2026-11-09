<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\Serializer\Strategy;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Api\Registry\HeadElementFactoryRegistryInterface;
use Hryvinskyi\HeadTagManager\Model\Serializer\StorableAttributes;
use Hryvinskyi\HeadTagManager\Model\Serializer\Strategy\AbstractSerializationStrategy;
use Hryvinskyi\HeadTagManager\Test\Unit\Model\Serializer\Stub\ConcreteSerializationStrategy;
use Hryvinskyi\HeadTagManager\Test\Unit\Model\Serializer\Stub\ConcreteSerializationStrategyWithAdditionalData;
use Hryvinskyi\HeadTagManager\Test\Unit\Model\Serializer\Stub\TestElement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AbstractSerializationStrategyTest extends TestCase
{
    /** @var HeadElementFactoryRegistryInterface&MockObject */
    private HeadElementFactoryRegistryInterface $factoryRegistry;

    /** @var ConcreteSerializationStrategy */
    private ConcreteSerializationStrategy $strategy;

    protected function setUp(): void
    {
        $this->factoryRegistry = $this->createMock(HeadElementFactoryRegistryInterface::class);
        $this->strategy = new ConcreteSerializationStrategy($this->factoryRegistry, new StorableAttributes());
    }

    public function testCanHandleReturnsTrue(): void
    {
        $element = new TestElement();
        $this->assertTrue($this->strategy->canHandle($element));
    }

    public function testCanHandleReturnsFalse(): void
    {
        $element = $this->createMock(HeadElementInterface::class);
        $this->assertFalse($this->strategy->canHandle($element));
    }

    public function testSerializeStoresAStringableAttributeValueAsItsText(): void
    {
        $element = $this->createMock(HeadElementInterface::class);
        $element->method('getAttributes')->willReturn([
            'href' => new class () implements \Stringable {
                public function __toString(): string
                {
                    return '/styles.css';
                }
            },
            'data' => ['variant' => 'hero'],
        ]);

        $data = $this->strategy->serialize($element, 'key');

        $this->assertSame(['href' => '/styles.css', 'data' => ['variant' => 'hero']], $data['attributes']);
    }

    public function testSerializeBasicData(): void
    {
        $element = new TestElement();
        $element->setAttributes(['name' => 'test', 'content' => 'value']);

        $result = $this->strategy->serialize($element, 'test-key');

        $expected = [
            'type' => TestElement::class,
            'short_type' => 'test',
            'attributes' => ['name' => 'test', 'content' => 'value'],
            'sort_order' => 100
        ];

        $this->assertEquals($expected, $result);
    }

    public function testSerializeWithAdditionalData(): void
    {
        $element = new TestElement();
        $element->setAttributes(['name' => 'test']);

        $strategy = new ConcreteSerializationStrategyWithAdditionalData(
            $this->factoryRegistry,
            new StorableAttributes()
        );
        $result = $strategy->serialize($element, 'test-key');

        $expected = [
            'type' => TestElement::class,
            'short_type' => 'test',
            'attributes' => ['name' => 'test'],
            'sort_order' => 100,
            'additional' => 'data'
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetPriorityReturnsDefaultValue(): void
    {
        $this->assertEquals(100, $this->strategy->getPriority());
    }
}
