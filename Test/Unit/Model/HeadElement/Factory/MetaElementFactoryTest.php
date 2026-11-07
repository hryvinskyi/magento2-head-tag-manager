<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\HeadElement\Factory;

use Hryvinskyi\HeadTagManager\Model\HeadElement\Factory\MetaElementFactory;
use Hryvinskyi\HeadTagManager\Model\HeadElement\MetaElement;
use Hryvinskyi\HeadTagManager\Model\HeadElement\MetaElementFactory as MetaElementObjectFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test for MetaElementFactory
 */
class MetaElementFactoryTest extends TestCase
{
    /**
     * @var MetaElementObjectFactory&MockObject
     */
    private MockObject $elementFactory;

    /**
     * @var MetaElementFactory
     */
    private MetaElementFactory $factory;

    protected function setUp(): void
    {
        $this->elementFactory = $this->createMock(MetaElementObjectFactory::class);
        $this->factory = new MetaElementFactory($this->elementFactory);
    }

    /**
     * The generated factory is asked with the element's constructor parameter names and its element is returned
     */
    public function testAsksTheGeneratedFactoryWithConstructorParameterNames(): void
    {
        $element = $this->createMock(MetaElement::class);
        $this->elementFactory->expects($this->once())
            ->method('create')
            ->with([
                'attributes' => ['name' => 'description', 'content' => 'Test'],
                'sortOrder' => 7,
            ])
            ->willReturn($element);

        $this->assertSame($element, $this->factory->create([
            'attributes' => ['name' => 'description', 'content' => 'Test'],
            'sort_order' => 7,
        ]));
    }

    /**
     * Keys the element does not know are not passed on, and what is missing takes the defaults
     */
    public function testAppliesDefaultsAndIgnoresUnknownKeys(): void
    {
        $element = $this->createMock(MetaElement::class);
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
        $this->assertEquals('meta', $this->factory->getElementType());
    }

    /**
     * Test getElementClassName returns correct class name
     */
    public function testGetElementClassName(): void
    {
        $this->assertEquals(MetaElement::class, $this->factory->getElementClassName());
    }
}
