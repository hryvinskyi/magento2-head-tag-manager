<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
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

    public function testDefaultSortOrderIs100()
    {
        $this->assertEquals(100, $this->abstractHeadElement->getSortOrder());
    }

    public function testCanSetAndGetSortOrder()
    {
        $this->abstractHeadElement->setSortOrder(50);
        $this->assertEquals(50, $this->abstractHeadElement->getSortOrder());
    }

    public function testSetSortOrderReturnsSelf()
    {
        $result = $this->abstractHeadElement->setSortOrder(25);
        $this->assertSame($this->abstractHeadElement, $result);
    }

    public function testConstructorAcceptsSortOrder()
    {
        $element = $this->getMockForAbstractClass(
            AbstractHeadElement::class,
            [$this->secureHtmlRenderer, [], 42]
        );
        $this->assertEquals(42, $element->getSortOrder());
    }
}