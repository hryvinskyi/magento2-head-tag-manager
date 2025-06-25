<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\Serializer\Strategy;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Api\Registry\HeadElementFactoryRegistryInterface;
use Hryvinskyi\HeadTagManager\Model\Serializer\Strategy\AbstractSerializationStrategy;
use PHPUnit\Framework\TestCase;

class AbstractSerializationStrategyTest extends TestCase
{
    private HeadElementFactoryRegistryInterface $factoryRegistry;
    private ConcreteSerializationStrategy $strategy;

    protected function setUp(): void
    {
        $this->factoryRegistry = $this->createMock(HeadElementFactoryRegistryInterface::class);
        $this->strategy = new ConcreteSerializationStrategy($this->factoryRegistry);
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

    public function testSerializeBasicData(): void
    {
        $element = new TestElement();
        $element->setAttributes(['name' => 'test', 'content' => 'value']);

        $result = $this->strategy->serialize($element, 'test-key');

        $expected = [
            'type' => TestElement::class,
            'short_type' => 'test',
            'attributes' => ['name' => 'test', 'content' => 'value']
        ];

        $this->assertEquals($expected, $result);
    }

    public function testSerializeWithAdditionalData(): void
    {
        $element = new TestElement();
        $element->setAttributes(['name' => 'test']);

        $strategy = new ConcreteSerializationStrategyWithAdditionalData($this->factoryRegistry);
        $result = $strategy->serialize($element, 'test-key');

        $expected = [
            'type' => TestElement::class,
            'short_type' => 'test',
            'attributes' => ['name' => 'test'],
            'additional' => 'data'
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetPriorityReturnsDefaultValue(): void
    {
        $this->assertEquals(100, $this->strategy->getPriority());
    }
}

// Test concrete implementations
class ConcreteSerializationStrategy extends AbstractSerializationStrategy
{
    public function getElementType(): string
    {
        return 'test';
    }

    public function getElementClassName(): string
    {
        return TestElement::class;
    }
}

class ConcreteSerializationStrategyWithAdditionalData extends AbstractSerializationStrategy
{
    public function getElementType(): string
    {
        return 'test';
    }

    public function getElementClassName(): string
    {
        return TestElement::class;
    }

    protected function getAdditionalSerializationData(HeadElementInterface $element): array
    {
        return ['additional' => 'data'];
    }
}

class TestElement implements HeadElementInterface
{
    private array $attributes = [];

    public function setAttribute(string $name, string $value): self
    {
        $this->attributes[$name] = $value;
        return $this;
    }

    public function setAttributes(array $attributes): self
    {
        $this->attributes = $attributes;
        return $this;
    }

    public function getAttribute(string $name, ?string $default = null): ?string
    {
        return $this->attributes[$name] ?? $default;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function hasAttribute(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    public function removeAttribute(string $name): self
    {
        unset($this->attributes[$name]);
        return $this;
    }

    public function render(): string
    {
        return '<test>';
    }
}