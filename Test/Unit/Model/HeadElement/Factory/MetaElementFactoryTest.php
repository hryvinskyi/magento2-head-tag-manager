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
use Hryvinskyi\HeadTagManager\Test\Unit\Model\HeadElement\Fixture\ElementServices;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MetaElementFactoryTest extends TestCase
{
    /**
     * @var MetaElementObjectFactory&MockObject
     */
    private MockObject $elementFactory;

    /**
     * @var list<mixed> the data of every call to the generated factory
     */
    private array $generatedFactoryCalls = [];

    /**
     * @var MetaElementFactory
     */
    private MetaElementFactory $factory;

    protected function setUp(): void
    {
        $services = new ElementServices();
        $this->elementFactory = $this->createMock(MetaElementObjectFactory::class);
        $this->elementFactory->method('create')->willReturnCallback(
            function (mixed ...$arguments) use ($services): MetaElement {
                $this->generatedFactoryCalls[] = $arguments[0] ?? [];

                return new MetaElement($services->attributeRenderer());
            }
        );
        $this->factory = new MetaElementFactory($this->elementFactory);
    }

    /**
     * The caller's data is applied through the element, so none of it reaches the object manager
     */
    public function testAppliesTheDataThroughTheElement(): void
    {
        $element = $this->factory->create([
            'attributes' => ['name' => 'robots', 'content' => 'noindex'],
            'sort_order' => 7,
        ]);

        $this->assertSame([[]], $this->generatedFactoryCalls);
        $this->assertInstanceOf(MetaElement::class, $element);
        $this->assertSame(['name' => 'robots', 'content' => 'noindex'], $element->getAttributes());
        $this->assertSame(7, $element->getSortOrder());
        $this->assertSame('<meta name="robots" content="noindex">', $element->render());
    }

    /**
     * Attribute data the object manager would read as instructions is kept as plain attribute values
     */
    public function testKeepsAttributeValuesTheObjectManagerWouldInterpret(): void
    {
        $element = $this->factory->create(['attributes' => ['data' => ['instance' => 'hero'], 'argument' => 'x']]);

        $this->assertSame([[]], $this->generatedFactoryCalls);
        $this->assertSame(['data' => ['instance' => 'hero'], 'argument' => 'x'], $element->getAttributes());
    }

    /**
     * What the data leaves out takes the element's defaults
     */
    public function testAppliesDefaultsForMissingData(): void
    {
        $element = $this->factory->create();

        $this->assertInstanceOf(MetaElement::class, $element);
        $this->assertSame([], $element->getAttributes());
        $this->assertSame(100, $element->getSortOrder());
    }

    public function testGetElementType(): void
    {
        $this->assertSame('meta', $this->factory->getElementType());
    }

    public function testGetElementClassName(): void
    {
        $this->assertSame(MetaElement::class, $this->factory->getElementClassName());
    }
}
