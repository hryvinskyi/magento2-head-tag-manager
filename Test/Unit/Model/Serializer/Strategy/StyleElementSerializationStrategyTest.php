<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\Serializer\Strategy;

use Hryvinskyi\HeadTagManager\Api\Registry\HeadElementFactoryRegistryInterface;
use Hryvinskyi\HeadTagManager\Model\HeadElement\StyleElement;
use Hryvinskyi\HeadTagManager\Model\Serializer\Strategy\StyleElementSerializationStrategy;
use PHPUnit\Framework\TestCase;

class StyleElementSerializationStrategyTest extends TestCase
{
    private HeadElementFactoryRegistryInterface $factoryRegistry;
    private StyleElementSerializationStrategy $strategy;

    protected function setUp(): void
    {
        $this->factoryRegistry = $this->createMock(HeadElementFactoryRegistryInterface::class);
        $this->strategy = new StyleElementSerializationStrategy($this->factoryRegistry);
    }

    public function testGetElementType(): void
    {
        $this->assertEquals('style', $this->strategy->getElementType());
    }

    public function testGetElementClassName(): void
    {
        $this->assertEquals(StyleElement::class, $this->strategy->getElementClassName());
    }

    public function testCanHandleStyleElement(): void
    {
        $element = $this->createMock(StyleElement::class);
        $this->assertTrue($this->strategy->canHandle($element));
    }

    public function testSerializeWithContent(): void
    {
        $element = $this->createMock(StyleElement::class);
        $element->method('getAttributes')->willReturn(['type' => 'text/css']);
        $element->method('getContent')->willReturn('body { color: red; }');

        $result = $this->strategy->serialize($element, 'test-key');

        $expected = [
            'type' => get_class($element),
            'short_type' => 'style',
            'attributes' => ['type' => 'text/css'],
            'content' => 'body { color: red; }'
        ];

        $this->assertEquals($expected, $result);
    }

    public function testSerializeWithoutContent(): void
    {
        $element = $this->createMock(StyleElement::class);
        $element->method('getAttributes')->willReturn(['type' => 'text/css']);
        $element->method('getContent')->willReturn(null);

        $result = $this->strategy->serialize($element, 'test-key');

        $expected = [
            'type' => get_class($element),
            'short_type' => 'style',
            'attributes' => ['type' => 'text/css'],
            'content' => null
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetPriority(): void
    {
        $this->assertEquals(100, $this->strategy->getPriority());
    }
}