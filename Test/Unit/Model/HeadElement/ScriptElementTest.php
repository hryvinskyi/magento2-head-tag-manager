<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\HeadElement;

use Hryvinskyi\Base\Api\Html\AttributeRendererInterface;
use Hryvinskyi\HeadTagManager\Model\HeadElement\ScriptElement;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ScriptElementTest extends TestCase
{
    /**
     * @var SecureHtmlRenderer&MockObject
     */
    private MockObject $secureHtmlRenderer;

    /**
     * @var ScriptElement
     */
    private ScriptElement $scriptElement;

    protected function setUp(): void
    {
        $this->secureHtmlRenderer = $this->createMock(SecureHtmlRenderer::class);
        $this->scriptElement = new ScriptElement(
            $this->secureHtmlRenderer,
            $this->createMock(AttributeRendererInterface::class)
        );
    }

    public function testCanSetAndGetContent(): void
    {
        $content = 'console.log("test");';
        $this->scriptElement->setContent($content);

        $this->assertEquals($content, $this->scriptElement->getContent());
    }

    public function testRendersScriptWithContent(): void
    {
        $content = 'console.log("test");';
        $this->scriptElement->setContent($content);
        $this->scriptElement->setAttributes([]);
        $this->secureHtmlRenderer->expects($this->once())
            ->method('renderTag')
            ->with('script', [], $content, false)
            ->willReturn('<script>console.log("test");</script>');

        $this->assertEquals('<script>console.log("test");</script>', $this->scriptElement->render());
    }

    public function testRendersScriptWithAttributes(): void
    {
        $attributes = ['src' => 'script.js', 'async' => 'async'];
        $this->scriptElement->setAttributes($attributes);
        $this->secureHtmlRenderer->expects($this->once())
            ->method('renderTag')
            ->with('script', $attributes, '', false)
            ->willReturn('<script src="script.js" async="async"></script>');

        $this->assertEquals('<script src="script.js" async="async"></script>', $this->scriptElement->render());
    }

    public function testConstructorTakesAttributesContentAndSortOrder(): void
    {
        $element = new ScriptElement(
            $this->secureHtmlRenderer,
            $this->createMock(AttributeRendererInterface::class),
            ['src' => 'script.js'],
            'console.log("test");',
            7
        );

        $this->assertSame(['src' => 'script.js'], $element->getAttributes());
        $this->assertSame('console.log("test");', $element->getContent());
        $this->assertSame(7, $element->getSortOrder());
    }
}
