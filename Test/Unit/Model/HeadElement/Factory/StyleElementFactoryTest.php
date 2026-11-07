<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\HeadElement\Factory;

use Hryvinskyi\HeadTagManager\Model\HeadElement\Factory\StyleElementFactory;
use Hryvinskyi\HeadTagManager\Model\HeadElement\StyleElement;
use Hryvinskyi\HeadTagManager\Model\HeadElement\StyleElementFactory as StyleElementObjectFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test for StyleElementFactory
 */
class StyleElementFactoryTest extends TestCase
{
    /**
     * @var StyleElementObjectFactory&MockObject
     */
    private MockObject $elementFactory;

    /**
     * @var StyleElementFactory
     */
    private StyleElementFactory $factory;

    protected function setUp(): void
    {
        $this->elementFactory = $this->createMock(StyleElementObjectFactory::class);
        $this->factory = new StyleElementFactory($this->elementFactory);
    }

    /**
     * The generated factory is asked with the element's constructor parameter names and its element is returned
     */
    public function testAsksTheGeneratedFactoryWithConstructorParameterNames(): void
    {
        $element = $this->createMock(StyleElement::class);
        $this->elementFactory->expects($this->once())
            ->method('create')
            ->with([
                'attributes' => ['media' => 'screen'],
                'content' => 'code();',
                'sortOrder' => 7,
            ])
            ->willReturn($element);

        $this->assertSame($element, $this->factory->create([
            'attributes' => ['media' => 'screen'],
            'content' => 'code();',
            'sort_order' => 7,
        ]));
    }

    /**
     * Keys the element does not know are not passed on, and what is missing takes the defaults
     */
    public function testAppliesDefaultsAndIgnoresUnknownKeys(): void
    {
        $element = $this->createMock(StyleElement::class);
        $this->elementFactory->expects($this->once())
            ->method('create')
            ->with([
                'attributes' => [],
                'content' => null,
                'sortOrder' => 10,
            ])
            ->willReturn($element);

        $this->assertSame($element, $this->factory->create(['other_field' => 'ignored']));
    }

    /**
     * Test getElementType returns correct type
     */
    public function testGetElementType(): void
    {
        $this->assertEquals('style', $this->factory->getElementType());
    }

    /**
     * Test getElementClassName returns correct class name
     */
    public function testGetElementClassName(): void
    {
        $this->assertEquals(StyleElement::class, $this->factory->getElementClassName());
    }
}
