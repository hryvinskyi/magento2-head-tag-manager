<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\HeadElement;

use Hryvinskyi\HeadTagManager\Model\HeadElement\LinkElement;
use Hryvinskyi\HeadTagManager\Test\Unit\Model\HeadElement\Fixture\RealAttributeRenderer;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use PHPUnit\Framework\TestCase;

class LinkElementTest extends TestCase
{
    /**
     * @var LinkElement
     */
    private LinkElement $linkElement;

    protected function setUp(): void
    {
        $this->linkElement = new LinkElement(
            $this->createMock(SecureHtmlRenderer::class),
            (new RealAttributeRenderer())->build()
        );
    }

    public function testRendersLinkElementWithAttributes(): void
    {
        $this->linkElement->setAttributes([
            'rel' => 'stylesheet',
            'href' => 'styles.css'
        ]);

        /** @noinspection HtmlUnknownTarget */
        $this->assertSame(
            '<link href="styles.css" rel="stylesheet">',
            $this->linkElement->render()
        );
    }

    public function testRendersEmptyLinkElementWithNoAttributes(): void
    {
        $this->assertSame('<link>', $this->linkElement->render());
    }

    public function testProperlyEscapesAttributeValues(): void
    {
        $this->linkElement->setAttribute('href', 'test.css?param="dangerous"');

        $this->assertStringContainsString('href="test.css?param=&quot;dangerous&quot;"', $this->linkElement->render());
    }

    public function testRendersWithoutClosingSlashInThePreferredAttributeOrder(): void
    {
        $this->linkElement->setAttributes([
            'href' => '/f.woff2',
            'as' => 'font',
            'rel' => 'preload',
            'type' => 'font/woff2'
        ]);

        /** @noinspection HtmlUnknownTarget */
        $this->assertSame(
            '<link type="font/woff2" href="/f.woff2" rel="preload" as="font">',
            $this->linkElement->render()
        );
    }
}
