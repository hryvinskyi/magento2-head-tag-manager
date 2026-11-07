<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\HeadElement;

use Hryvinskyi\Base\Api\Html\AttributeRendererInterface;
use Hryvinskyi\Base\Exception\InvalidParamException;
use Hryvinskyi\HeadTagManager\Model\HeadElement\AbstractHeadElement;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AbstractHeadElementTest extends TestCase
{
    /**
     * @var SecureHtmlRenderer&MockObject
     */
    private MockObject $secureHtmlRenderer;

    /**
     * @var AttributeRendererInterface&MockObject
     */
    private MockObject $attributeRenderer;

    /**
     * @var AbstractHeadElement
     */
    private AbstractHeadElement $abstractHeadElement;

    protected function setUp(): void
    {
        $this->secureHtmlRenderer = $this->createMock(SecureHtmlRenderer::class);
        $this->attributeRenderer = $this->createMock(AttributeRendererInterface::class);
        $this->abstractHeadElement = $this->element();
    }

    /**
     * Builds the smallest concrete element, the way a third party extends the abstract class.
     *
     * @param array<string, string> $attributes
     * @param int $sortOrder
     * @return AbstractHeadElement
     */
    private function element(array $attributes = [], int $sortOrder = 100): AbstractHeadElement
    {
        return new class (
            $this->secureHtmlRenderer,
            $this->attributeRenderer,
            $attributes,
            $sortOrder
        ) extends AbstractHeadElement {
            /**
             * @inheritDoc
             */
            public function render(): string
            {
                return '<element' . $this->attributesToString() . '>';
            }
        };
    }

    public function testRendersItsAttributesThroughTheAttributeRenderer(): void
    {
        $this->attributeRenderer->expects($this->once())
            ->method('render')
            ->with(['id' => 'test-id'])
            ->willReturn(' id="test-id"');

        $this->assertSame('<element id="test-id">', $this->element(['id' => 'test-id'])->render());
    }

    public function testAnAttributeWithoutATextFormFailsAsTheRendererReportsIt(): void
    {
        $this->attributeRenderer->method('render')->willThrowException(new InvalidParamException('no text form'));

        $this->expectException(InvalidParamException::class);

        $this->abstractHeadElement->render();
    }

    public function testCanSetAndGetAttribute(): void
    {
        $this->abstractHeadElement->setAttribute('id', 'test-id');

        $this->assertEquals('test-id', $this->abstractHeadElement->getAttribute('id'));
    }

    public function testCanSetMultipleAttributes(): void
    {
        $attributes = [
            'id' => 'test-id',
            'class' => 'test-class'
        ];

        $this->abstractHeadElement->setAttributes($attributes);

        $this->assertEquals('test-id', $this->abstractHeadElement->getAttribute('id'));
        $this->assertEquals('test-class', $this->abstractHeadElement->getAttribute('class'));
    }

    public function testReturnsDefaultValueWhenAttributeDoesNotExist(): void
    {
        $this->assertEquals('default', $this->abstractHeadElement->getAttribute('non-existent', 'default'));
    }

    public function testCanCheckIfAttributeExists(): void
    {
        $this->abstractHeadElement->setAttribute('id', 'test-id');

        $this->assertTrue($this->abstractHeadElement->hasAttribute('id'));
        $this->assertFalse($this->abstractHeadElement->hasAttribute('non-existent'));
    }

    public function testCanRemoveAttribute(): void
    {
        $this->abstractHeadElement->setAttribute('id', 'test-id');
        $this->abstractHeadElement->removeAttribute('id');

        $this->assertFalse($this->abstractHeadElement->hasAttribute('id'));
    }

    public function testCanGetAllAttributes(): void
    {
        $attributes = [
            'id' => 'test-id',
            'class' => 'test-class'
        ];

        $this->abstractHeadElement->setAttributes($attributes);

        $this->assertEquals($attributes, $this->abstractHeadElement->getAttributes());
    }

    public function testCanGetSecureHtmlRenderer(): void
    {
        $this->assertSame($this->secureHtmlRenderer, $this->abstractHeadElement->getSecureHtmlRenderer());
    }

    public function testDefaultSortOrderIs100(): void
    {
        $this->assertEquals(100, $this->abstractHeadElement->getSortOrder());
    }

    public function testCanSetAndGetSortOrder(): void
    {
        $this->abstractHeadElement->setSortOrder(50);
        $this->assertEquals(50, $this->abstractHeadElement->getSortOrder());
    }

    public function testSetSortOrderReturnsSelf(): void
    {
        $result = $this->abstractHeadElement->setSortOrder(25);
        $this->assertSame($this->abstractHeadElement, $result);
    }

    public function testConstructorAcceptsSortOrder(): void
    {
        $this->assertEquals(42, $this->element([], 42)->getSortOrder());
    }
}
