<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\Serializer\Strategy;

use Hryvinskyi\HeadTagManager\Api\Registry\HeadElementFactoryRegistryInterface;
use Hryvinskyi\HeadTagManager\Model\HeadElement\ScriptElement;
use Hryvinskyi\HeadTagManager\Model\Serializer\Strategy\ScriptElementSerializationStrategy;
use PHPUnit\Framework\TestCase;

class ScriptElementSerializationStrategyTest extends TestCase
{
    private HeadElementFactoryRegistryInterface $factoryRegistry;
    private ScriptElementSerializationStrategy $strategy;

    protected function setUp(): void
    {
        $this->factoryRegistry = $this->createMock(HeadElementFactoryRegistryInterface::class);
        $this->strategy = new ScriptElementSerializationStrategy($this->factoryRegistry);
    }

    public function testGetElementType(): void
    {
        $this->assertEquals('script', $this->strategy->getElementType());
    }

    public function testGetElementClassName(): void
    {
        $this->assertEquals(ScriptElement::class, $this->strategy->getElementClassName());
    }

    public function testCanHandleScriptElement(): void
    {
        $element = $this->createMock(ScriptElement::class);
        $this->assertTrue($this->strategy->canHandle($element));
    }

    public function testSerializeWithContent(): void
    {
        $element = $this->createMock(ScriptElement::class);
        $element->method('getAttributes')->willReturn(['src' => 'test.js']);
        $element->method('getContent')->willReturn('console.log("test");');

        $result = $this->strategy->serialize($element, 'test-key');

        $expected = [
            'type' => get_class($element),
            'short_type' => 'script',
            'attributes' => ['src' => 'test.js'],
            'content' => 'console.log("test");'
        ];

        $this->assertEquals($expected, $result);
    }

    public function testSerializeWithoutContent(): void
    {
        $element = $this->createMock(ScriptElement::class);
        $element->method('getAttributes')->willReturn(['src' => 'test.js']);
        $element->method('getContent')->willReturn(null);

        $result = $this->strategy->serialize($element, 'test-key');

        $expected = [
            'type' => get_class($element),
            'short_type' => 'script',
            'attributes' => ['src' => 'test.js'],
            'content' => null
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetPriority(): void
    {
        $this->assertEquals(100, $this->strategy->getPriority());
    }
}