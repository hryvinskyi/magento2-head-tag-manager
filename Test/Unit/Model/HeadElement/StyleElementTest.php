<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\HeadElement;

use Hryvinskyi\Base\Exception\InvalidParamException;
use Hryvinskyi\HeadTagManager\Model\HeadElement\StyleElement;
use Hryvinskyi\HeadTagManager\Test\Unit\Model\HeadElement\Fixture\ElementServices;
use PHPUnit\Framework\TestCase;

class StyleElementTest extends TestCase
{
    /**
     * @var ElementServices
     */
    private ElementServices $services;

    /**
     * @var StyleElement
     */
    private StyleElement $styleElement;

    protected function setUp(): void
    {
        $this->services = new ElementServices();
        $this->styleElement = new StyleElement($this->services->secureHtmlRenderer());
    }

    public function testCanSetAndGetContent(): void
    {
        $content = 'body { color: red; }';
        $this->styleElement->setContent($content);

        $this->assertSame($content, $this->styleElement->getContent());
    }

    public function testRendersStyleWithContent(): void
    {
        $this->styleElement->setContent('body { color: red; }');

        $this->assertSame('<style>body { color: red; }</style>', $this->styleElement->render());
    }

    public function testRendersStyleWithAttributes(): void
    {
        $this->styleElement->setAttributes(['type' => 'text/css', 'media' => 'screen']);

        $this->assertSame('<style type="text/css" media="screen"></style>', $this->styleElement->render());
    }

    /**
     * `false` and `null` leave the attribute out, `true` gives its boolean form, numbers and `Stringable`s their text
     */
    public function testRendersEveryAttributeValueTypeByOneRule(): void
    {
        $this->styleElement->setAttributes([
            'media' => 'screen',
            'disabled' => false,
            'nonce' => null,
            'blocking' => true,
            'data-version' => 2,
            'data-weight' => 0.5,
            'title' => $this->stringable('Theme'),
        ]);

        $this->assertSame(
            '<style title="Theme" media="screen" blocking="" data-version="2" data-weight="0.5"></style>',
            $this->styleElement->render()
        );
    }

    /**
     * An array has no text form; the element reports it instead of rendering "Array"
     */
    public function testAnArrayAttributeValueFailsToRender(): void
    {
        $this->styleElement->setAttribute('media', ['screen', 'print']);

        $this->expectException(InvalidParamException::class);

        $this->styleElement->render();
    }

    public function testConstructorTakesAttributesContentAndSortOrder(): void
    {
        $element = new StyleElement(
            $this->services->secureHtmlRenderer(),
            ['media' => 'screen'],
            'body { color: red; }',
            7
        );

        $this->assertSame(['media' => 'screen'], $element->getAttributes());
        $this->assertSame('body { color: red; }', $element->getContent());
        $this->assertSame(7, $element->getSortOrder());
    }

    /**
     * A style built without a sort order takes the same default as one built by its factory
     */
    public function testDefaultSortOrderIs10(): void
    {
        $this->assertSame(10, $this->styleElement->getSortOrder());
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
}
