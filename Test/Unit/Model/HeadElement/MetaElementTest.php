<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\HeadElement;

use Hryvinskyi\HeadTagManager\Model\HeadElement\MetaElement;
use Hryvinskyi\HeadTagManager\Test\Unit\Model\HeadElement\Fixture\ElementServices;
use PHPUnit\Framework\TestCase;

class MetaElementTest extends TestCase
{
    /**
     * @var MetaElement
     */
    private MetaElement $metaElement;

    protected function setUp(): void
    {
        $this->metaElement = new MetaElement((new ElementServices())->attributeRenderer());
    }

    public function testRendersMetaElementWithAttributes(): void
    {
        $this->metaElement->setAttributes([
            'name' => 'description',
            'content' => 'Page description'
        ]);

        $this->assertSame(
            '<meta name="description" content="Page description">',
            $this->metaElement->render()
        );
    }

    public function testRendersEmptyMetaElementWithNoAttributes(): void
    {
        $this->assertSame('<meta>', $this->metaElement->render());
    }

    public function testRendersCharsetMetaElement(): void
    {
        $this->metaElement->setAttribute('charset', 'UTF-8');

        $this->assertSame('<meta charset="UTF-8">', $this->metaElement->render());
    }

    public function testEncodesAttributeValues(): void
    {
        $this->metaElement->setAttributes(['name' => 'description', 'content' => 'Tom & "Jerry" <b>\'s</b>']);

        $this->assertSame(
            '<meta name="description" content="Tom &amp; &quot;Jerry&quot; &lt;b&gt;&#039;s&lt;/b&gt;">',
            $this->metaElement->render()
        );
    }
}
