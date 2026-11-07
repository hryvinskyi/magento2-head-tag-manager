<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\HeadElement;

use Hryvinskyi\Base\Api\Html\AttributeRendererInterface;
use Hryvinskyi\HeadTagManager\Model\HeadElement\StyleElement;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StyleElementTest extends TestCase
{
    /**
     * @var SecureHtmlRenderer&MockObject
     */
    private MockObject $secureHtmlRenderer;

    /**
     * @var StyleElement
     */
    private StyleElement $styleElement;

    protected function setUp(): void
    {
        $this->secureHtmlRenderer = $this->createMock(SecureHtmlRenderer::class);
        $this->styleElement = new StyleElement(
            $this->secureHtmlRenderer,
            $this->createMock(AttributeRendererInterface::class)
        );
    }

    public function testCanSetAndGetContent(): void
    {
        $content = 'body { color: red; }';
        $this->styleElement->setContent($content);

        $this->assertEquals($content, $this->styleElement->getContent());
    }

    public function testRendersStyleWithContent(): void
    {
        $content = 'body { color: red; }';
        $this->styleElement->setContent($content);
        $this->secureHtmlRenderer->expects($this->once())
            ->method('renderTag')
            ->with('style', [], $content, false)
            ->willReturn('<style>body { color: red; }</style>');

        $this->assertEquals('<style>body { color: red; }</style>', $this->styleElement->render());
    }

    public function testRendersStyleWithAttributes(): void
    {
        $attributes = ['type' => 'text/css', 'media' => 'screen'];
        $this->styleElement->setAttributes($attributes);
        $this->secureHtmlRenderer->expects($this->once())
            ->method('renderTag')
            ->with('style', $attributes, '', false)
            ->willReturn('<style type="text/css" media="screen"></style>');

        $this->assertEquals('<style type="text/css" media="screen"></style>', $this->styleElement->render());
    }

    public function testConstructorTakesAttributesContentAndSortOrder(): void
    {
        $element = new StyleElement(
            $this->secureHtmlRenderer,
            $this->createMock(AttributeRendererInterface::class),
            ['media' => 'screen'],
            'body { color: red; }',
            7
        );

        $this->assertSame(['media' => 'screen'], $element->getAttributes());
        $this->assertSame('body { color: red; }', $element->getContent());
        $this->assertSame(7, $element->getSortOrder());
    }
}
