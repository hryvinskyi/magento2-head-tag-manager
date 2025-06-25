<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model;

use Hryvinskyi\HeadTagManager\Api\Cache\HeadElementCacheStrategyInterface;
use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Api\Registry\HeadElementFactoryRegistryInterface;
use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementFactoryInterface;
use Hryvinskyi\HeadTagManager\Model\HeadElement\LinkElement;
use Hryvinskyi\HeadTagManager\Model\HeadElement\MetaElement;
use Hryvinskyi\HeadTagManager\Model\HeadElement\ScriptElement;
use Hryvinskyi\HeadTagManager\Model\HeadElement\StyleElement;
use Hryvinskyi\HeadTagManager\Model\HeadTagManager;
use PHPUnit\Framework\TestCase;

class HeadTagManagerTest extends TestCase
{
    private $factoryRegistry;
    private $cacheStrategy;
    private $headTagManager;

    protected function setUp(): void
    {
        $this->factoryRegistry = $this->createMock(HeadElementFactoryRegistryInterface::class);
        $this->cacheStrategy = $this->createMock(HeadElementCacheStrategyInterface::class);

        // Setup cache strategy to return empty array for load and true for save
        $this->cacheStrategy->method('load')->willReturn([]);
        $this->cacheStrategy->method('save')->willReturn(true);

        $this->headTagManager = new HeadTagManager(
            $this->factoryRegistry,
            $this->cacheStrategy
        );
    }

    public function testCanAddAndGetElement()
    {
        $element = $this->createMock(HeadElementInterface::class);
        $this->headTagManager->addElement($element, 'test-key');

        $this->assertTrue($this->headTagManager->hasElement('test-key'));
        $this->assertSame($element, $this->headTagManager->getElement('test-key'));
    }

    public function testCanRemoveElement()
    {
        $element = $this->createMock(HeadElementInterface::class);
        $this->headTagManager->addElement($element, 'test-key');
        $this->headTagManager->removeElement('test-key');

        $this->assertFalse($this->headTagManager->hasElement('test-key'));
        $this->assertNull($this->headTagManager->getElement('test-key'));
    }

    public function testCanCreateElement()
    {
        $element = $this->createMock(HeadElementInterface::class);
        $factory = $this->createMock(HeadElementFactoryInterface::class);
        
        $this->factoryRegistry->expects($this->once())
            ->method('getFactoryByType')
            ->with('meta')
            ->willReturn($factory);
            
        $factory->expects($this->once())
            ->method('create')
            ->with(['attributes' => ['name' => 'test']])
            ->willReturn($element);

        $result = $this->headTagManager->createElement('meta', ['attributes' => ['name' => 'test']], 'test-key');

        $this->assertSame($element, $result);
        $this->assertTrue($this->headTagManager->hasElement('test-key'));
    }

    public function testCreateElementThrowsExceptionForUnknownType()
    {
        $this->factoryRegistry->expects($this->once())
            ->method('getFactoryByType')
            ->with('unknown')
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No factory found for element type: unknown');

        $this->headTagManager->createElement('unknown', []);
    }

    public function testCreateElementGeneratesKeyAutomatically()
    {
        $element = $this->createMock(HeadElementInterface::class);
        $factory = $this->createMock(HeadElementFactoryInterface::class);
        
        $this->factoryRegistry->expects($this->once())
            ->method('getFactoryByType')
            ->with('meta')
            ->willReturn($factory);
            
        $factory->expects($this->once())
            ->method('create')
            ->with(['attributes' => ['name' => 'test']])
            ->willReturn($element);

        $result = $this->headTagManager->createElement('meta', ['attributes' => ['name' => 'test']]);

        $this->assertSame($element, $result);
        
        // Check that element was added with a generated key
        $allElements = $this->headTagManager->getAllElements();
        $this->assertCount(1, $allElements);
        $this->assertContains($element, $allElements);
        
        // The key should be generated based on type and data
        $expectedKey = 'meta_' . md5(serialize(['type' => 'meta', 'data' => ['attributes' => ['name' => 'test']]]));
        $this->assertTrue($this->headTagManager->hasElement($expectedKey));
    }

    public function testCanAddMetaWithAttributes()
    {
        $metaElement = $this->createMock(MetaElement::class);
        $factory = $this->createMock(HeadElementFactoryInterface::class);
        
        $this->factoryRegistry->expects($this->once())
            ->method('getFactoryByType')
            ->with('meta')
            ->willReturn($factory);
            
        $factory->expects($this->once())
            ->method('create')
            ->with(['attributes' => ['name' => 'description', 'content' => 'Test']])
            ->willReturn($metaElement);

        $this->headTagManager->addMeta(['name' => 'description', 'content' => 'Test'], 'meta-key');

        $this->assertTrue($this->headTagManager->hasElement('meta-key'));
    }

    public function testCanAddMetaWithNameAndContent()
    {
        $metaElement = $this->createMock(MetaElement::class);
        $factory = $this->createMock(HeadElementFactoryInterface::class);
        
        $this->factoryRegistry->expects($this->once())
            ->method('getFactoryByType')
            ->with('meta')
            ->willReturn($factory);
            
        $factory->expects($this->once())
            ->method('create')
            ->with(['attributes' => ['name' => 'description', 'content' => 'Test']])
            ->willReturn($metaElement);

        $this->headTagManager->addMetaName('description', 'Test');

        // Check that element was added with generated key
        $expectedKey = 'meta_' . md5(serialize(['type' => 'meta', 'data' => ['attributes' => ['name' => 'description', 'content' => 'Test']]]));
        $this->assertTrue($this->headTagManager->hasElement($expectedKey));
    }

    public function testCanAddMetaWithPropertyAndContent()
    {
        $metaElement = $this->createMock(MetaElement::class);
        $factory = $this->createMock(HeadElementFactoryInterface::class);
        
        $this->factoryRegistry->expects($this->once())
            ->method('getFactoryByType')
            ->with('meta')
            ->willReturn($factory);
            
        $factory->expects($this->once())
            ->method('create')
            ->with(['attributes' => ['property' => 'og:title', 'content' => 'Test']])
            ->willReturn($metaElement);

        $this->headTagManager->addMetaProperty('og:title', 'Test');

        // Check that element was added with generated key
        $expectedKey = 'meta_' . md5(serialize(['type' => 'meta', 'data' => ['attributes' => ['property' => 'og:title', 'content' => 'Test']]]));
        $this->assertTrue($this->headTagManager->hasElement($expectedKey));
    }

    public function testCanAddCharset()
    {
        $metaElement = $this->createMock(MetaElement::class);
        $factory = $this->createMock(HeadElementFactoryInterface::class);
        
        $this->factoryRegistry->expects($this->once())
            ->method('getFactoryByType')
            ->with('meta')
            ->willReturn($factory);
            
        $factory->expects($this->once())
            ->method('create')
            ->with(['attributes' => ['charset' => 'UTF-8']])
            ->willReturn($metaElement);

        $this->headTagManager->addCharset();

        $this->assertTrue($this->headTagManager->hasElement('charset'));
    }

    public function testCanAddStylesheet()
    {
        $linkElement = $this->createMock(LinkElement::class);
        $factory = $this->createMock(HeadElementFactoryInterface::class);
        
        $this->factoryRegistry->expects($this->once())
            ->method('getFactoryByType')
            ->with('link')
            ->willReturn($factory);
            
        $factory->expects($this->once())
            ->method('create')
            ->with(['attributes' => ['rel' => 'stylesheet', 'href' => 'styles.css']])
            ->willReturn($linkElement);

        $this->headTagManager->addStylesheet('styles.css');

        // Check that element was added with generated key
        $expectedKey = 'link_' . md5(serialize(['type' => 'link', 'data' => ['attributes' => ['rel' => 'stylesheet', 'href' => 'styles.css']]]));
        $this->assertTrue($this->headTagManager->hasElement($expectedKey));
    }

    public function testCanAddExternalScript()
    {
        $scriptElement = $this->createMock(ScriptElement::class);
        $factory = $this->createMock(HeadElementFactoryInterface::class);
        
        $this->factoryRegistry->expects($this->once())
            ->method('getFactoryByType')
            ->with('script')
            ->willReturn($factory);
            
        $factory->expects($this->once())
            ->method('create')
            ->with(['attributes' => ['src' => 'script.js']])
            ->willReturn($scriptElement);

        $this->headTagManager->addExternalScript('script.js');

        // Check that element was added with generated key
        $expectedKey = 'script_' . md5(serialize(['type' => 'script', 'data' => ['attributes' => ['src' => 'script.js']]]));
        $this->assertTrue($this->headTagManager->hasElement($expectedKey));
    }

    public function testCanAddInlineScript()
    {
        $content = 'console.log("test");';
        $scriptElement = $this->createMock(ScriptElement::class);
        $factory = $this->createMock(HeadElementFactoryInterface::class);
        
        $this->factoryRegistry->expects($this->once())
            ->method('getFactoryByType')
            ->with('script')
            ->willReturn($factory);
            
        $factory->expects($this->once())
            ->method('create')
            ->with(['attributes' => [], 'content' => $content])
            ->willReturn($scriptElement);

        $this->headTagManager->addInlineScript($content);

        // Check that element was added with generated key
        $expectedKey = 'script_' . md5(serialize(['type' => 'script', 'data' => ['attributes' => [], 'content' => $content]]));
        $this->assertTrue($this->headTagManager->hasElement($expectedKey));
    }

    public function testCanAddInlineStyle()
    {
        $content = 'body { color: red; }';
        $styleElement = $this->createMock(StyleElement::class);
        $factory = $this->createMock(HeadElementFactoryInterface::class);
        
        $this->factoryRegistry->expects($this->once())
            ->method('getFactoryByType')
            ->with('style')
            ->willReturn($factory);
            
        $factory->expects($this->once())
            ->method('create')
            ->with(['attributes' => [], 'content' => $content])
            ->willReturn($styleElement);

        $this->headTagManager->addInlineStyle($content);

        // Check that element was added with generated key
        $expectedKey = 'style_' . md5(serialize(['type' => 'style', 'data' => ['attributes' => [], 'content' => $content]]));
        $this->assertTrue($this->headTagManager->hasElement($expectedKey));
    }

    public function testCenderConcatenatesAllElementRenderings()
    {
        $element1 = $this->createMock(HeadElementInterface::class);
        $element1->expects($this->once())
            ->method('render')
            ->willReturn('<meta name="description" content="Test">');

        $element2 = $this->createMock(HeadElementInterface::class);
        $element2->expects($this->once())
            ->method('render')
            ->willReturn('<link rel="stylesheet" href="styles.css">');

        $this->headTagManager->addElement($element1, 'meta');
        $this->headTagManager->addElement($element2, 'link');

        $expected = "<meta name=\"description\" content=\"Test\">" . PHP_EOL .
            "<link rel=\"stylesheet\" href=\"styles.css\">" . PHP_EOL;
        $this->assertEquals($expected, $this->headTagManager->render());
    }

    public function testGetRenderedElementsReturnsArrayOfRenderedElements()
    {
        $element1 = $this->createMock(HeadElementInterface::class);
        $element1->expects($this->once())
            ->method('render')
            ->willReturn('<meta name="description" content="Test">');

        $element2 = $this->createMock(HeadElementInterface::class);
        $element2->expects($this->once())
            ->method('render')
            ->willReturn('<link rel="stylesheet" href="styles.css">');

        $this->headTagManager->addElement($element1, 'meta');
        $this->headTagManager->addElement($element2, 'link');

        $expected = [
            'meta' => '<meta name="description" content="Test">',
            'link' => '<link rel="stylesheet" href="styles.css">'
        ];
        $this->assertEquals($expected, $this->headTagManager->getRenderedElements());
    }

    public function testClearRemovesAllElementsAndClearsCache()
    {
        $element = $this->createMock(HeadElementInterface::class);
        $this->headTagManager->addElement($element, 'test-key');

        $this->cacheStrategy->expects($this->once())
            ->method('clear');

        $this->headTagManager->clear();

        $this->assertFalse($this->headTagManager->hasElement('test-key'));
        $this->assertEmpty($this->headTagManager->getAllElements());
    }

    public function testGetAllElementsReturnsAllElements()
    {
        $element1 = $this->createMock(HeadElementInterface::class);
        $element2 = $this->createMock(HeadElementInterface::class);

        $this->headTagManager->addElement($element1, 'key1');
        $this->headTagManager->addElement($element2, 'key2');

        $allElements = $this->headTagManager->getAllElements();

        $this->assertCount(2, $allElements);
        $this->assertSame($element1, $allElements['key1']);
        $this->assertSame($element2, $allElements['key2']);
    }

    public function testSaveToCacheCallsCacheStrategy()
    {
        $element = $this->createMock(HeadElementInterface::class);
        $this->headTagManager->addElement($element, 'test-key');

        $this->cacheStrategy->expects($this->once())
            ->method('save')
            ->with($this->headTagManager->getAllElements());

        $this->headTagManager->saveToCache();
    }

    public function testRenderCallsSaveToCacheOnlyIfModified()
    {
        $element = $this->createMock(HeadElementInterface::class);
        $element->method('render')->willReturn('<meta>');
        $this->headTagManager->addElement($element, 'test-key');

        $this->cacheStrategy->expects($this->once())
            ->method('save');

        $this->headTagManager->render();
    }

    public function testRenderDoesNotCallSaveToCacheIfNotModified()
    {
        // Don't add any elements, so elements are not modified
        $this->cacheStrategy->expects($this->never())
            ->method('save');

        $this->headTagManager->render();
    }

    public function testGetRenderedElementsCallsSaveToCacheOnlyIfModified()
    {
        $element = $this->createMock(HeadElementInterface::class);
        $element->method('render')->willReturn('<meta>');
        $this->headTagManager->addElement($element, 'test-key');

        $this->cacheStrategy->expects($this->once())
            ->method('save');

        $this->headTagManager->getRenderedElements();
    }

    public function testGetRenderedElementsDoesNotCallSaveToCacheIfNotModified()
    {
        // Don't add any elements, so elements are not modified
        $this->cacheStrategy->expects($this->never())
            ->method('save');

        $this->headTagManager->getRenderedElements();
    }

    public function testLoadFromCacheWhenCacheReturnsElements()
    {
        $cachedElement = $this->createMock(HeadElementInterface::class);
        $cachedElement->method('render')->willReturn('<cached>');
        
        // Create a new mock cache strategy that returns cached elements
        $newCacheStrategy = $this->createMock(HeadElementCacheStrategyInterface::class);
        $newCacheStrategy->method('load')->willReturn(['cached_key' => $cachedElement]);
        $newCacheStrategy->method('save')->willReturn(true);
        
        // Create new instance to test cache loading
        $headTagManager = new HeadTagManager(
            $this->factoryRegistry,
            $newCacheStrategy
        );

        $this->assertTrue($headTagManager->hasElement('cached_key'));
        $this->assertSame($cachedElement, $headTagManager->getElement('cached_key'));
    }
}