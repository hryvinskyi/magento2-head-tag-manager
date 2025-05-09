<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Model\HeadElement\LinkElement;
use Hryvinskyi\HeadTagManager\Model\HeadElement\LinkElementFactory;
use Hryvinskyi\HeadTagManager\Model\HeadElement\MetaElement;
use Hryvinskyi\HeadTagManager\Model\HeadElement\MetaElementFactory;
use Hryvinskyi\HeadTagManager\Model\HeadElement\ScriptElement;
use Hryvinskyi\HeadTagManager\Model\HeadElement\ScriptElementFactory;
use Hryvinskyi\HeadTagManager\Model\HeadElement\StyleElement;
use Hryvinskyi\HeadTagManager\Model\HeadElement\StyleElementFactory;
use Hryvinskyi\HeadTagManager\Model\HeadTagManager;
use PHPUnit\Framework\TestCase;

class HeadTagManagerTest extends TestCase
{
    private $linkElementFactory;
    private $metaElementFactory;
    private $scriptElementFactory;
    private $styleElementFactory;
    private $headTagManager;

    protected function setUp(): void
    {
        $this->linkElementFactory = $this->createMock(LinkElementFactory::class);
        $this->metaElementFactory = $this->createMock(MetaElementFactory::class);
        $this->scriptElementFactory = $this->createMock(ScriptElementFactory::class);
        $this->styleElementFactory = $this->createMock(StyleElementFactory::class);

        $this->headTagManager = new HeadTagManager(
            $this->linkElementFactory,
            $this->metaElementFactory,
            $this->scriptElementFactory,
            $this->styleElementFactory
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

    public function testCanAddMetaWithAttributes()
    {
        $metaElement = $this->createMock(MetaElement::class);
        $this->metaElementFactory->expects($this->once())
            ->method('create')
            ->with(['attributes' => ['name' => 'description', 'content' => 'Test']])
            ->willReturn($metaElement);

        $this->headTagManager->addMeta(['name' => 'description', 'content' => 'Test'], 'meta-key');

        $this->assertTrue($this->headTagManager->hasElement('meta-key'));
    }

    public function testCanAddMetaWithNameAndContent()
    {
        $metaElement = $this->createMock(MetaElement::class);
        $this->metaElementFactory->expects($this->once())
            ->method('create')
            ->with(['attributes' => ['name' => 'description', 'content' => 'Test']])
            ->willReturn($metaElement);

        $this->headTagManager->addMetaName('description', 'Test');

        $this->assertTrue($this->headTagManager->hasElement('meta_description'));
    }

    public function testCanAddMetaWithPropertyAndContent()
    {
        $metaElement = $this->createMock(MetaElement::class);
        $this->metaElementFactory->expects($this->once())
            ->method('create')
            ->with(['attributes' => ['property' => 'og:title', 'content' => 'Test']])
            ->willReturn($metaElement);

        $this->headTagManager->addMetaProperty('og:title', 'Test');

        $this->assertTrue($this->headTagManager->hasElement('meta_og:title'));
    }

    public function testCanAddCharset()
    {
        $metaElement = $this->createMock(MetaElement::class);
        $this->metaElementFactory->expects($this->once())
            ->method('create')
            ->with(['attributes' => ['charset' => 'UTF-8']])
            ->willReturn($metaElement);

        $this->headTagManager->addCharset();

        $this->assertTrue($this->headTagManager->hasElement('charset'));
    }

    public function testCanAddStylesheet()
    {
        $linkElement = $this->createMock(LinkElement::class);
        $this->linkElementFactory->expects($this->once())
            ->method('create')
            ->with(['attributes' => ['rel' => 'stylesheet', 'href' => 'styles.css']])
            ->willReturn($linkElement);

        $this->headTagManager->addStylesheet('styles.css');

        $this->assertTrue($this->headTagManager->hasElement('css_' . md5('styles.css')));
    }

    public function testCanAddExternalScript()
    {
        $scriptElement = $this->createMock(ScriptElement::class);
        $this->scriptElementFactory->expects($this->once())
            ->method('create')
            ->with(['attributes' => ['src' => 'script.js']])
            ->willReturn($scriptElement);

        $this->headTagManager->addExternalScript('script.js');

        $this->assertTrue($this->headTagManager->hasElement('script_' . md5('script.js')));
    }

    public function testCanAddInlineScript()
    {
        $content = 'console.log("test");';
        $scriptElement = $this->createMock(ScriptElement::class);
        $this->scriptElementFactory->expects($this->once())
            ->method('create')
            ->with(['attributes' => [], 'content' => $content])
            ->willReturn($scriptElement);

        $this->headTagManager->addInlineScript($content);

        $this->assertTrue($this->headTagManager->hasElement('script_inline_' . md5($content)));
    }

    public function testCanAddStyle()
    {
        $content = 'body { color: red; }';
        $styleElement = $this->createMock(StyleElement::class);
        $this->styleElementFactory->expects($this->once())
            ->method('create')
            ->with(['attributes' => [], 'content' => $content])
            ->willReturn($styleElement);

        $this->headTagManager->addStyle($content);

        $this->assertTrue($this->headTagManager->hasElement('style_' . md5($content)));
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
}