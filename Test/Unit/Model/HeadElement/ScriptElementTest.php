<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\HeadElement;

use Hryvinskyi\HeadTagManager\Model\HeadElement\ScriptElement;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use PHPUnit\Framework\TestCase;

class ScriptElementTest extends TestCase
{
    private $secureHtmlRenderer;
    private $scriptElement;

    protected function setUp(): void
    {
        $this->secureHtmlRenderer = $this->createMock(SecureHtmlRenderer::class);
        $this->scriptElement = new ScriptElement($this->secureHtmlRenderer);
    }

    public function testCanSetAndGetContent()
    {
        $content = 'console.log("test");';
        $this->scriptElement->setContent($content);

        $this->assertEquals($content, $this->scriptElement->getContent());
    }

    public function testRendersScriptWithContent()
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

    public function testRendersScriptWithAttributes()
    {
        $attributes = ['src' => 'script.js', 'async' => 'async'];
        $this->scriptElement->setAttributes($attributes);
        $this->secureHtmlRenderer->expects($this->once())
            ->method('renderTag')
            ->with('script', $attributes, '', false)
            ->willReturn('<script src="script.js" async="async"></script>');

        $this->assertEquals('<script src="script.js" async="async"></script>', $this->scriptElement->render());
    }
}