<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\HeadElement\Factory;

use Hryvinskyi\HeadTagManager\Model\HeadElement\Factory\LinkElementFactory;
use Hryvinskyi\HeadTagManager\Model\HeadElement\LinkElement;
use Hryvinskyi\HeadTagManager\Model\HeadElement\LinkElementFactory as LinkElementObjectFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test for LinkElementFactory
 */
class LinkElementFactoryTest extends TestCase
{
    /**
     * @var LinkElementObjectFactory&MockObject
     */
    private MockObject $elementFactory;

    /**
     * @var LinkElementFactory
     */
    private LinkElementFactory $factory;

    protected function setUp(): void
    {
        $this->elementFactory = $this->createMock(LinkElementObjectFactory::class);
        $this->factory = new LinkElementFactory($this->elementFactory);
    }

    /**
     * The generated factory is asked with the element's constructor parameter names and its element is returned
     */
    public function testAsksTheGeneratedFactoryWithConstructorParameterNames(): void
    {
        $element = $this->createMock(LinkElement::class);
        $this->elementFactory->expects($this->once())
            ->method('create')
            ->with([
                'attributes' => ['rel' => 'stylesheet', 'href' => 'styles.css'],
                'sortOrder' => 7,
            ])
            ->willReturn($element);

        $this->assertSame($element, $this->factory->create([
            'attributes' => ['rel' => 'stylesheet', 'href' => 'styles.css'],
            'sort_order' => 7,
        ]));
    }

    /**
     * Keys the element does not know are not passed on, and what is missing takes the defaults
     */
    public function testAppliesDefaultsAndIgnoresUnknownKeys(): void
    {
        $element = $this->createMock(LinkElement::class);
        $this->elementFactory->expects($this->once())
            ->method('create')
            ->with([
                'attributes' => [],
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
        $this->assertEquals('link', $this->factory->getElementType());
    }

    /**
     * Test getElementClassName returns correct class name
     */
    public function testGetElementClassName(): void
    {
        $this->assertEquals(LinkElement::class, $this->factory->getElementClassName());
    }
}
