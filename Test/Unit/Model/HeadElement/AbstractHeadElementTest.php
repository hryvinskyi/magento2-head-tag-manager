<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\HeadElement;

use Hryvinskyi\HeadTagManager\Model\HeadElement\AbstractHeadElement;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use PHPUnit\Framework\TestCase;

class AbstractHeadElementTest extends TestCase
{
    private $secureHtmlRenderer;
    private $abstractHeadElement;

    protected function setUp(): void
    {
        $this->secureHtmlRenderer = $this->createMock(SecureHtmlRenderer::class);
        $this->abstractHeadElement = $this->getMockForAbstractClass(
            AbstractHeadElement::class,
            [$this->secureHtmlRenderer]
        );
    }

    public function testCanSetAndGetAttribute()
    {
        $this->abstractHeadElement->setAttribute('id', 'test-id');

        $this->assertEquals('test-id', $this->abstractHeadElement->getAttribute('id'));
    }

    public function testCanSetMultipleAttributes()
    {
        $attributes = [
            'id' => 'test-id',
            'class' => 'test-class'
        ];

        $this->abstractHeadElement->setAttributes($attributes);

        $this->assertEquals('test-id', $this->abstractHeadElement->getAttribute('id'));
        $this->assertEquals('test-class', $this->abstractHeadElement->getAttribute('class'));
    }

    public function testReturnsDefaultValueWhenAttributeDoesNotExist()
    {
        $this->assertEquals('default', $this->abstractHeadElement->getAttribute('non-existent', 'default'));
    }

    public function testCanCheckIfAttributeExists()
    {
        $this->abstractHeadElement->setAttribute('id', 'test-id');

        $this->assertTrue($this->abstractHeadElement->hasAttribute('id'));
        $this->assertFalse($this->abstractHeadElement->hasAttribute('non-existent'));
    }

    public function testCanRemoveAttribute()
    {
        $this->abstractHeadElement->setAttribute('id', 'test-id');
        $this->abstractHeadElement->removeAttribute('id');

        $this->assertFalse($this->abstractHeadElement->hasAttribute('id'));
    }

    public function testCanGetAllAttributes()
    {
        $attributes = [
            'id' => 'test-id',
            'class' => 'test-class'
        ];

        $this->abstractHeadElement->setAttributes($attributes);

        $this->assertEquals($attributes, $this->abstractHeadElement->getAttributes());
    }

    public function testCanGetSecureHtmlRenderer()
    {
        $this->assertSame($this->secureHtmlRenderer, $this->abstractHeadElement->getSecureHtmlRenderer());
    }
}