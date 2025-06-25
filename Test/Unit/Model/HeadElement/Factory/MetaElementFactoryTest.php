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
use Hryvinskyi\HeadTagManager\Model\HeadElement\MetaElementFactory as MagentoMetaElementFactory;
use PHPUnit\Framework\TestCase;

class MetaElementFactoryTest extends TestCase
{
    private MagentoMetaElementFactory $magentoFactory;
    private MetaElementFactory $factory;

    protected function setUp(): void
    {
        $this->magentoFactory = $this->createMock(MagentoMetaElementFactory::class);
        $this->factory = new MetaElementFactory($this->magentoFactory);
    }

    public function testCreate(): void
    {
        $data = ['attributes' => ['name' => 'description', 'content' => 'Test']];
        $element = $this->createMock(MetaElement::class);

        $this->magentoFactory->expects($this->once())
            ->method('create')
            ->with($data)
            ->willReturn($element);

        $result = $this->factory->create($data);

        $this->assertSame($element, $result);
    }

    public function testGetElementType(): void
    {
        $this->assertEquals('meta', $this->factory->getElementType());
    }

    public function testGetElementClassName(): void
    {
        $this->assertEquals(MetaElement::class, $this->factory->getElementClassName());
    }
}