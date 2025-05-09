<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\HeadElement;

use Hryvinskyi\HeadTagManager\Model\HeadElement\MetaElement;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use PHPUnit\Framework\TestCase;

class MetaElementTest extends TestCase
{
    private $secureHtmlRenderer;
    private $metaElement;

    protected function setUp(): void
    {
        $this->secureHtmlRenderer = $this->createMock(SecureHtmlRenderer::class);
        $this->metaElement = new MetaElement($this->secureHtmlRenderer);
    }

    public function testRendersMetaElementWithAttributes()
    {
        $this->metaElement->setAttributes([
            'name' => 'description',
            'content' => 'Page description'
        ]);

        $this->assertEquals(
            '<meta name="description" content="Page description">',
            $this->metaElement->render()
        );
    }

    public function testRendersEmptyMetaElementWithNoAttributes()
    {
        $this->assertEquals('<meta>', $this->metaElement->render());
    }

    public function testRendersCharsetMetaElement()
    {
        $this->metaElement->setAttribute('charset', 'UTF-8');

        $this->assertEquals('<meta charset="UTF-8">', $this->metaElement->render());
    }
}