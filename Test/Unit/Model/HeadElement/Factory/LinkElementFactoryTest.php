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
use Hryvinskyi\HeadTagManager\Test\Unit\Model\HeadElement\Fixture\ElementServices;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LinkElementFactoryTest extends TestCase
{
    /**
     * @var LinkElementObjectFactory&MockObject
     */
    private MockObject $elementFactory;

    /**
     * @var list<mixed> the data of every call to the generated factory
     */
    private array $generatedFactoryCalls = [];

    /**
     * @var LinkElementFactory
     */
    private LinkElementFactory $factory;

    protected function setUp(): void
    {
        $services = new ElementServices();
        $this->elementFactory = $this->createMock(LinkElementObjectFactory::class);
        $this->elementFactory->method('create')->willReturnCallback(
            function (mixed ...$arguments) use ($services): LinkElement {
                $this->generatedFactoryCalls[] = $arguments[0] ?? [];

                return new LinkElement($services->attributeRenderer(), $services->secureHtmlRenderer());
            }
        );
        $this->factory = new LinkElementFactory($this->elementFactory);
    }

    /**
     * The caller's data is applied through the element, so none of it reaches the object manager
     */
    public function testAppliesTheDataThroughTheElement(): void
    {
        $element = $this->factory->create([
            'attributes' => ['rel' => 'stylesheet', 'href' => 'styles.css'],
            'sort_order' => 7,
        ]);

        $this->assertSame([[]], $this->generatedFactoryCalls);
        $this->assertInstanceOf(LinkElement::class, $element);
        $this->assertSame(['rel' => 'stylesheet', 'href' => 'styles.css'], $element->getAttributes());
        $this->assertSame(7, $element->getSortOrder());
        $this->assertSame('<link href="styles.css" rel="stylesheet">', $element->render());
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

        $this->assertInstanceOf(LinkElement::class, $element);
        $this->assertSame([], $element->getAttributes());
        $this->assertSame(100, $element->getSortOrder());
    }

    /**
     * A numeric sort order, as a layout argument passes it, becomes an int; anything else takes the type's default
     *
     * @dataProvider sortOrderProvider
     * @param mixed $sortOrder the sort order passed
     * @param int $expected the sort order the element takes
     */
    public function testTheSortOrderIsReadAsTheObjectManagerUsedToCoerceIt(mixed $sortOrder, int $expected): void
    {
        $element = $this->createFromUntypedData(['sort_order' => $sortOrder]);

        $this->assertSame($expected, $element->getSortOrder());
    }

    /**
     * Sort orders as callers pass them, and the one the element takes
     *
     * @return array<string, array{0: mixed, 1: int}>
     */
    public static function sortOrderProvider(): array
    {
        return [
            'int' => [7, 7],
            'numeric string' => ['7', 7],
            'negative numeric string' => ['-3', -3],
            'decimal string' => ['7.9', 7],
            'float' => [5.0, 5],
            'non-numeric string' => ['first', 100],
            'empty string' => ['', 100],
            'null' => [null, 100],
        ];
    }

    /**
     * An attribute name that is a number, as an array key holding a numeric string always is, is applied as text
     */
    public function testANumericAttributeNameIsAppliedAsText(): void
    {
        $element = $this->createFromUntypedData(['attributes' => [7 => 'x', 'REL' => 'icon']]);

        $this->assertSame('x', $element->getAttribute('7'));
        $this->assertSame('icon', $element->getAttribute('rel'));
    }

    /**
     * A name made of digits is no HTML attribute; the link still renders on both paths instead of failing
     */
    public function testALinkWithANumericAttributeNameStillRendersThroughEitherPath(): void
    {
        $remote = $this->createFromUntypedData(
            ['attributes' => [7 => 'x', 'rel' => 'stylesheet', 'href' => 'https://cdn.example.com/a.css']]
        );
        $local = $this->createFromUntypedData(['attributes' => [7 => 'x', 'rel' => 'icon', 'href' => '/a.ico']]);

        $this->assertStringContainsString('href="https://cdn.example.com/a.css"', $remote->render());
        $this->assertStringContainsString('href="/a.ico"', $local->render());
    }

    public function testGetElementType(): void
    {
        $this->assertSame('link', $this->factory->getElementType());
    }

    public function testGetElementClassName(): void
    {
        $this->assertSame(LinkElement::class, $this->factory->getElementClassName());
    }

    /**
     * Creates an element from data no static type reaches, as the object manager passes layout arguments
     *
     * @param array<mixed> $data
     * @return LinkElement
     */
    private function createFromUntypedData(array $data): LinkElement
    {
        $element = (new \ReflectionMethod($this->factory, 'create'))->invoke($this->factory, $data);
        $this->assertInstanceOf(LinkElement::class, $element);

        return $element;
    }
}
