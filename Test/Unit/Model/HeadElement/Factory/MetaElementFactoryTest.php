<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\HeadElement\Factory;

use Hryvinskyi\HeadTagManager\Model\HeadElement\Factory\MetaElementFactory;
use Hryvinskyi\HeadTagManager\Model\HeadElement\MetaElement;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test for MetaElementFactory
 */
class MetaElementFactoryTest extends TestCase
{
    private SecureHtmlRenderer|MockObject $secureHtmlRendererMock;
    private MetaElementFactory $factory;

    protected function setUp(): void
    {
        $this->secureHtmlRendererMock = $this->createMock(SecureHtmlRenderer::class);
        $this->factory = new MetaElementFactory($this->secureHtmlRendererMock);
    }

    /**
     * Test creating a MetaElement with attributes
     */
    public function testCreate(): void
    {
        $data = ['attributes' => ['name' => 'description', 'content' => 'Test']];

        $result = $this->factory->create($data);

        $this->assertInstanceOf(MetaElement::class, $result);
        $this->assertEquals(['name' => 'description', 'content' => 'Test'], $result->getAttributes());
    }

    /**
     * Test creating a MetaElement without attributes
     */
    public function testCreateWithoutAttributes(): void
    {
        $data = [];

        $result = $this->factory->create($data);

        $this->assertInstanceOf(MetaElement::class, $result);
        $this->assertEquals([], $result->getAttributes());
    }

    /**
     * Test creating a MetaElement with custom data structure
     */
    public function testCreateWithCustomData(): void
    {
        $data = [
            'attributes' => ['property' => 'og:title', 'content' => 'My Title'],
            'other_field' => 'ignored' // Should be ignored
        ];

        $result = $this->factory->create($data);

        $this->assertInstanceOf(MetaElement::class, $result);
        $this->assertEquals(['property' => 'og:title', 'content' => 'My Title'], $result->getAttributes());
    }

    /**
     * Test getElementType returns correct type
     */
    public function testGetElementType(): void
    {
        $this->assertEquals('meta', $this->factory->getElementType());
    }

    /**
     * Test getElementClassName returns correct class name
     */
    public function testGetElementClassName(): void
    {
        $this->assertEquals(MetaElement::class, $this->factory->getElementClassName());
    }
}