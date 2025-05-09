<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\HeadElement;

use Hryvinskyi\HeadTagManager\Model\HeadElement\LinkElement;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use PHPUnit\Framework\TestCase;

class LinkElementTest extends TestCase
{
    private $secureHtmlRenderer;
    private $linkElement;

    protected function setUp(): void
    {
        $this->secureHtmlRenderer = $this->createMock(SecureHtmlRenderer::class);
        $this->linkElement = new LinkElement($this->secureHtmlRenderer);
    }

    public function testRendersLinkElementWithAttributes()
    {
        $this->linkElement->setAttributes([
            'rel' => 'stylesheet',
            'href' => 'styles.css'
        ]);

        $this->assertEquals(
            '<link rel="stylesheet" href="styles.css">',
            $this->linkElement->render()
        );
    }

    public function testRendersEmptyLinkElementWithNoAttributes()
    {
        $this->assertEquals('<link>', $this->linkElement->render());
    }

    public function testProperlyEscapesAttributeValues()
    {
        $this->linkElement->setAttribute('href', 'test.css?param="dangerous"');

        $this->assertStringContainsString('href="test.css?param=&quot;dangerous&quot;"', $this->linkElement->render());
    }
}