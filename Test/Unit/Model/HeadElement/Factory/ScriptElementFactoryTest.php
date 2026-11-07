<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\HeadElement\Factory;

use Hryvinskyi\HeadTagManager\Model\HeadElement\Factory\ScriptElementFactory;
use Hryvinskyi\HeadTagManager\Model\HeadElement\ScriptElement;
use Hryvinskyi\HeadTagManager\Model\HeadElement\ScriptElementFactory as ScriptElementObjectFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test for ScriptElementFactory
 */
class ScriptElementFactoryTest extends TestCase
{
    /**
     * @var ScriptElementObjectFactory&MockObject
     */
    private MockObject $elementFactory;

    /**
     * @var ScriptElementFactory
     */
    private ScriptElementFactory $factory;

    protected function setUp(): void
    {
        $this->elementFactory = $this->createMock(ScriptElementObjectFactory::class);
        $this->factory = new ScriptElementFactory($this->elementFactory);
    }

    /**
     * The generated factory is asked with the element's constructor parameter names and its element is returned
     */
    public function testAsksTheGeneratedFactoryWithConstructorParameterNames(): void
    {
        $element = $this->createMock(ScriptElement::class);
        $this->elementFactory->expects($this->once())
            ->method('create')
            ->with([
                'attributes' => ['type' => 'text/javascript'],
                'content' => 'code();',
                'sortOrder' => 7,
            ])
            ->willReturn($element);

        $this->assertSame($element, $this->factory->create([
            'attributes' => ['type' => 'text/javascript'],
            'content' => 'code();',
            'sort_order' => 7,
        ]));
    }

    /**
     * Keys the element does not know are not passed on, and what is missing takes the defaults
     */
    public function testAppliesDefaultsAndIgnoresUnknownKeys(): void
    {
        $element = $this->createMock(ScriptElement::class);
        $this->elementFactory->expects($this->once())
            ->method('create')
            ->with([
                'attributes' => [],
                'content' => null,
                'sortOrder' => 100,
            ])
            ->willReturn($element);

        $this->assertSame($element, $this->factory->create(['other_field' => 'ignored']));
    }

    /**
     * Test getElementType returns correct type
     */
    public function testGetElementType(): void
    {
        $this->assertEquals('script', $this->factory->getElementType());
    }

    /**
     * Test getElementClassName returns correct class name
     */
    public function testGetElementClassName(): void
    {
        $this->assertEquals(ScriptElement::class, $this->factory->getElementClassName());
    }
}
