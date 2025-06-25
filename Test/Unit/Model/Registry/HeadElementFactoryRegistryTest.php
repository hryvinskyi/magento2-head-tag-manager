<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\Registry;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementFactoryInterface;
use Hryvinskyi\HeadTagManager\Model\Registry\HeadElementFactoryRegistry;
use PHPUnit\Framework\TestCase;

class HeadElementFactoryRegistryTest extends TestCase
{
    private HeadElementFactoryRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new HeadElementFactoryRegistry();
    }

    public function testRegisterAndGetFactoryByType(): void
    {
        $factory = $this->createMock(HeadElementFactoryInterface::class);
        $factory->method('getElementType')->willReturn('meta');
        $factory->method('getElementClassName')->willReturn('MetaElement');

        $this->registry->registerFactory($factory);

        $this->assertSame($factory, $this->registry->getFactoryByType('meta'));
    }

    public function testRegisterAndGetFactoryByClassName(): void
    {
        $factory = $this->createMock(HeadElementFactoryInterface::class);
        $factory->method('getElementType')->willReturn('meta');
        $factory->method('getElementClassName')->willReturn('MetaElement');

        $this->registry->registerFactory($factory);

        $this->assertSame($factory, $this->registry->getFactoryByClassName('MetaElement'));
    }

    public function testGetElementTypeByClassName(): void
    {
        $factory = $this->createMock(HeadElementFactoryInterface::class);
        $factory->method('getElementType')->willReturn('meta');
        $factory->method('getElementClassName')->willReturn('MetaElement');

        $this->registry->registerFactory($factory);

        $this->assertEquals('meta', $this->registry->getElementTypeByClassName('MetaElement'));
    }

    public function testGetAllElementTypes(): void
    {
        $factory1 = $this->createMock(HeadElementFactoryInterface::class);
        $factory1->method('getElementType')->willReturn('meta');
        $factory1->method('getElementClassName')->willReturn('MetaElement');

        $factory2 = $this->createMock(HeadElementFactoryInterface::class);
        $factory2->method('getElementType')->willReturn('link');
        $factory2->method('getElementClassName')->willReturn('LinkElement');

        $this->registry->registerFactory($factory1);
        $this->registry->registerFactory($factory2);

        $types = $this->registry->getAllElementTypes();

        $this->assertCount(2, $types);
        $this->assertContains('meta', $types);
        $this->assertContains('link', $types);
    }

    public function testHasFactoryForType(): void
    {
        $factory = $this->createMock(HeadElementFactoryInterface::class);
        $factory->method('getElementType')->willReturn('meta');
        $factory->method('getElementClassName')->willReturn('MetaElement');

        $this->assertFalse($this->registry->hasFactoryForType('meta'));

        $this->registry->registerFactory($factory);

        $this->assertTrue($this->registry->hasFactoryForType('meta'));
        $this->assertFalse($this->registry->hasFactoryForType('link'));
    }

    public function testHasFactoryForClassName(): void
    {
        $factory = $this->createMock(HeadElementFactoryInterface::class);
        $factory->method('getElementType')->willReturn('meta');
        $factory->method('getElementClassName')->willReturn('MetaElement');

        $this->assertFalse($this->registry->hasFactoryForClassName('MetaElement'));

        $this->registry->registerFactory($factory);

        $this->assertTrue($this->registry->hasFactoryForClassName('MetaElement'));
        $this->assertFalse($this->registry->hasFactoryForClassName('LinkElement'));
    }

    public function testGetAllFactories(): void
    {
        $factory1 = $this->createMock(HeadElementFactoryInterface::class);
        $factory1->method('getElementType')->willReturn('meta');
        $factory1->method('getElementClassName')->willReturn('MetaElement');

        $factory2 = $this->createMock(HeadElementFactoryInterface::class);
        $factory2->method('getElementType')->willReturn('link');
        $factory2->method('getElementClassName')->willReturn('LinkElement');

        $this->registry->registerFactory($factory1);
        $this->registry->registerFactory($factory2);

        $factories = $this->registry->getAllFactories();

        $this->assertCount(2, $factories);
        $this->assertContains($factory1, $factories);
        $this->assertContains($factory2, $factories);
    }

    public function testClear(): void
    {
        $factory = $this->createMock(HeadElementFactoryInterface::class);
        $factory->method('getElementType')->willReturn('meta');
        $factory->method('getElementClassName')->willReturn('MetaElement');

        $this->registry->registerFactory($factory);
        $this->assertTrue($this->registry->hasFactoryForType('meta'));

        $this->registry->clear();

        $this->assertFalse($this->registry->hasFactoryForType('meta'));
        $this->assertEmpty($this->registry->getAllElementTypes());
        $this->assertEmpty($this->registry->getAllFactories());
    }

    public function testConstructorWithFactories(): void
    {
        $factory1 = $this->createMock(HeadElementFactoryInterface::class);
        $factory1->method('getElementType')->willReturn('meta');
        $factory1->method('getElementClassName')->willReturn('MetaElement');

        $factory2 = $this->createMock(HeadElementFactoryInterface::class);
        $factory2->method('getElementType')->willReturn('link');
        $factory2->method('getElementClassName')->willReturn('LinkElement');

        $registry = new HeadElementFactoryRegistry([$factory1, $factory2]);

        $this->assertTrue($registry->hasFactoryForType('meta'));
        $this->assertTrue($registry->hasFactoryForType('link'));
        $this->assertSame($factory1, $registry->getFactoryByType('meta'));
        $this->assertSame($factory2, $registry->getFactoryByType('link'));
    }

    public function testGetFactoryByTypeReturnsNullForUnknownType(): void
    {
        $this->assertNull($this->registry->getFactoryByType('unknown'));
    }

    public function testGetFactoryByClassNameReturnsNullForUnknownClass(): void
    {
        $this->assertNull($this->registry->getFactoryByClassName('UnknownElement'));
    }

    public function testGetElementTypeByClassNameReturnsNullForUnknownClass(): void
    {
        $this->assertNull($this->registry->getElementTypeByClassName('UnknownElement'));
    }
}