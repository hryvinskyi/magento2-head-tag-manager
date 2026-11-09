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
use Hryvinskyi\HeadTagManager\Test\Unit\Model\HeadElement\Fixture\ElementServices;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ScriptElementFactoryTest extends TestCase
{
    /**
     * @var ScriptElementObjectFactory&MockObject
     */
    private MockObject $elementFactory;

    /**
     * @var list<mixed> the data of every call to the generated factory
     */
    private array $generatedFactoryCalls = [];

    /**
     * @var ScriptElementFactory
     */
    private ScriptElementFactory $factory;

    protected function setUp(): void
    {
        $services = new ElementServices();
        $this->elementFactory = $this->createMock(ScriptElementObjectFactory::class);
        $this->elementFactory->method('create')->willReturnCallback(
            function (mixed ...$arguments) use ($services): ScriptElement {
                $this->generatedFactoryCalls[] = $arguments[0] ?? [];

                return new ScriptElement($services->attributeRenderer(), $services->secureHtmlRenderer());
            }
        );
        $this->factory = new ScriptElementFactory($this->elementFactory);
    }

    /**
     * The caller's data is applied through the element, so none of it reaches the object manager
     */
    public function testAppliesTheDataThroughTheElement(): void
    {
        $element = $this->factory->create([
            'attributes' => ['src' => 'app.js', 'defer' => true],
            'content' => 'code();',
            'sort_order' => 7,
        ]);

        $this->assertSame([[]], $this->generatedFactoryCalls);
        $this->assertInstanceOf(ScriptElement::class, $element);
        $this->assertSame(['src' => 'app.js', 'defer' => true], $element->getAttributes());
        $this->assertSame('code();', $element->getContent());
        $this->assertSame(7, $element->getSortOrder());
        $this->assertSame('<script src="app.js" defer="">code();</script>', $element->render());
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

        $this->assertInstanceOf(ScriptElement::class, $element);
        $this->assertSame([], $element->getAttributes());
        $this->assertNull($element->getContent());
        $this->assertSame(100, $element->getSortOrder());
    }

    /**
     * Scalar and \Stringable content becomes its text, as the object manager used to coerce it
     */
    public function testScalarAndStringableContentBecomesItsText(): void
    {
        $this->assertSame('42', $this->createFromUntypedData(['content' => 42])->getContent());
        $this->assertSame('1.5', $this->createFromUntypedData(['content' => 1.5])->getContent());
        $code = $this->stringable('run();');
        $this->assertSame('run();', $this->createFromUntypedData(['content' => $code])->getContent());
        $this->assertNull($this->createFromUntypedData(['content' => null])->getContent());
    }

    /**
     * Content with no text form is refused rather than dropped
     */
    public function testContentWithoutATextFormIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->createFromUntypedData(['content' => ['run();']]);
    }

    /**
     * A sort order that is not a number takes this type's default
     */
    public function testANonNumericSortOrderTakesTheTypeDefault(): void
    {
        $this->assertSame(100, $this->createFromUntypedData(['sort_order' => 'late'])->getSortOrder());
        $this->assertSame(3, $this->createFromUntypedData(['sort_order' => '3'])->getSortOrder());
    }

    /**
     * Builds a value object whose text form is the given string
     *
     * @param string $text
     * @return \Stringable
     */
    private function stringable(string $text): \Stringable
    {
        return new class ($text) implements \Stringable {
            /**
             * @param string $text
             */
            public function __construct(private readonly string $text)
            {
            }

            /**
             * @inheritDoc
             */
            public function __toString(): string
            {
                return $this->text;
            }
        };
    }

    public function testGetElementType(): void
    {
        $this->assertSame('script', $this->factory->getElementType());
    }

    public function testGetElementClassName(): void
    {
        $this->assertSame(ScriptElement::class, $this->factory->getElementClassName());
    }

    /**
     * Creates an element from data no static type reaches, as the object manager passes layout arguments
     *
     * @param array<mixed> $data
     * @return ScriptElement
     */
    private function createFromUntypedData(array $data): ScriptElement
    {
        $element = (new \ReflectionMethod($this->factory, 'create'))->invoke($this->factory, $data);
        $this->assertInstanceOf(ScriptElement::class, $element);

        return $element;
    }
}
