<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\HeadElement;

use Hryvinskyi\HeadTagManager\Model\HeadElement\StyleElement;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use PHPUnit\Framework\TestCase;

class StyleElementTest extends TestCase
{
    private $secureHtmlRenderer;
    private $styleElement;

    protected function setUp(): void
    {
        $this->secureHtmlRenderer = $this->createMock(SecureHtmlRenderer::class);
        $this->styleElement = new StyleElement($this->secureHtmlRenderer);
    }

    public function testCanSetAndGetContent()
    {
        $content = 'body { color: red; }';
        $this->styleElement->setContent($content);

        $this->assertEquals($content, $this->styleElement->getContent());
    }

    public function testRendersStyleWithContent()
    {
        $content = 'body { color: red; }';
        $this->styleElement->setContent($content);
        $this->secureHtmlRenderer->expects($this->once())
            ->method('renderTag')
            ->with('style', [], $content, false)
            ->willReturn('<style>body { color: red; }</style>');

        $this->assertEquals('<style>body { color: red; }</style>', $this->styleElement->render());
    }

    public function testRendersStyleWithAttributes()
    {
        $attributes = ['type' => 'text/css', 'media' => 'screen'];
        $this->styleElement->setAttributes($attributes);
        $this->secureHtmlRenderer->expects($this->once())
            ->method('renderTag')
            ->with('style', $attributes, '', false)
            ->willReturn('<style type="text/css" media="screen"></style>');

        $this->assertEquals('<style type="text/css" media="screen"></style>', $this->styleElement->render());
    }
}