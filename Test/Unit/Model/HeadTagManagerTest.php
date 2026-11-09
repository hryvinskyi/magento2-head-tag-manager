<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementFactoryInterface;
use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Api\Registry\HeadElementFactoryRegistryInterface;
use Hryvinskyi\HeadTagManager\Model\HeadElement\Factory\LinkElementFactory;
use Hryvinskyi\HeadTagManager\Model\HeadElement\Factory\MetaElementFactory;
use Hryvinskyi\HeadTagManager\Model\HeadElement\Factory\ScriptElementFactory;
use Hryvinskyi\HeadTagManager\Model\HeadElement\Factory\StyleElementFactory;
use Hryvinskyi\HeadTagManager\Model\HeadElement\LinkElement;
use Hryvinskyi\HeadTagManager\Model\HeadElement\LinkElementFactory as LinkElementObjectFactory;
use Hryvinskyi\HeadTagManager\Model\HeadElement\MetaElement;
use Hryvinskyi\HeadTagManager\Model\HeadElement\MetaElementFactory as MetaElementObjectFactory;
use Hryvinskyi\HeadTagManager\Model\HeadElement\ScriptElement;
use Hryvinskyi\HeadTagManager\Model\HeadElement\ScriptElementFactory as ScriptElementObjectFactory;
use Hryvinskyi\HeadTagManager\Model\HeadElement\StyleElement;
use Hryvinskyi\HeadTagManager\Model\HeadElement\StyleElementFactory as StyleElementObjectFactory;
use Hryvinskyi\HeadTagManager\Model\HeadTagManager;
use Hryvinskyi\HeadTagManager\Test\Unit\Model\HeadElement\Fixture\ElementServices;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class HeadTagManagerTest extends TestCase
{
    private const URL = 'https://cdn.example.com/app.js';
    private const CSS = 'https://cdn.example.com/app.css';

    /**
     * @var LoggerInterface&MockObject
     */
    private MockObject $logger;

    /**
     * @var HeadTagManager
     */
    private HeadTagManager $headTagManager;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->headTagManager = new HeadTagManager($this->registry(), new Json(), $this->logger);
    }

    public function testCanAddAndGetElement(): void
    {
        $element = $this->element('<meta name="a">');
        $this->headTagManager->addElement($element, 'test-key');

        $this->assertTrue($this->headTagManager->hasElement('test-key'));
        $this->assertSame($element, $this->headTagManager->getElement('test-key'));
    }

    public function testCanRemoveElement(): void
    {
        $this->headTagManager->addElement($this->element('<meta name="a">'), 'test-key');
        $this->headTagManager->removeElement('test-key');

        $this->assertFalse($this->headTagManager->hasElement('test-key'));
        $this->assertNull($this->headTagManager->getElement('test-key'));
    }

    public function testCreateElementAddsTheElementUnderTheGivenKey(): void
    {
        $element = $this->headTagManager->createElement('meta', ['attributes' => ['name' => 'test']], 'test-key');

        $this->assertInstanceOf(MetaElement::class, $element);
        $this->assertSame($element, $this->headTagManager->getElement('test-key'));
        $this->assertSame(['name' => 'test'], $element->getAttributes());
    }

    public function testCreateElementThrowsExceptionForUnknownType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No factory found for element type: unknown');

        $this->headTagManager->createElement('unknown', []);
    }

    public function testCreateElementWithoutAKeyUsesTheGeneratedKey(): void
    {
        $data = ['attributes' => ['name' => 'test']];

        $element = $this->headTagManager->createElement('meta', $data);

        $this->assertSame(
            [$this->headTagManager->generateElementKey('meta', $data) => $element],
            $this->headTagManager->getAllElements()
        );
    }

    public function testGeneratedKeyIsTheTypeAndAHash(): void
    {
        $this->assertMatchesRegularExpression(
            '/^script_[0-9a-f]{64}$/',
            $this->headTagManager->generateElementKey('script', ['attributes' => ['src' => self::URL]])
        );
    }

    public function testAnExternalScriptAddedTwoWaysRendersOnce(): void
    {
        $this->headTagManager->addExternalScript(self::URL);
        $this->headTagManager->addScript(['src' => self::URL]);

        $this->assertCount(1, $this->headTagManager->getAllElements());
        $this->assertSame(
            '<script src="https://cdn.example.com/app.js"></script>' . PHP_EOL,
            $this->headTagManager->render()
        );
    }

    public function testAStylesheetAddedTwoWaysRendersOnce(): void
    {
        $this->headTagManager->addStylesheet(self::CSS);
        $this->headTagManager->addLink(['rel' => 'stylesheet', 'href' => self::CSS]);

        $this->assertCount(1, $this->headTagManager->getAllElements());
        $this->assertSame(
            '<link href="https://cdn.example.com/app.css" rel="stylesheet" />' . PHP_EOL,
            $this->headTagManager->render()
        );
    }

    public function testAttributeNamesAreComparedWithoutCase(): void
    {
        $this->assertSame(
            $this->headTagManager->generateElementKey('script', ['attributes' => ['SRC' => self::URL]]),
            $this->headTagManager->generateElementKey('script', ['attributes' => ['src' => self::URL]])
        );
    }

    public function testAttributeOrderAbsentValuesAndSortOrderAreNotPartOfTheIdentity(): void
    {
        $this->assertSame(
            $this->headTagManager->generateElementKey('script', [
                'attributes' => ['defer' => true, 'src' => self::URL, 'async' => false, 'nonce' => null],
                'content' => '',
                'sort_order' => 5,
            ]),
            $this->headTagManager->generateElementKey('script', [
                'attributes' => ['src' => self::URL, 'defer' => true],
                'content' => null,
            ])
        );
    }

    public function testDifferentContentTypeOrValuesGiveDifferentKeys(): void
    {
        $keys = [
            $this->headTagManager->generateElementKey('script', ['attributes' => [], 'content' => 'a();']),
            $this->headTagManager->generateElementKey('script', ['attributes' => [], 'content' => 'b();']),
            $this->headTagManager->generateElementKey('style', ['attributes' => [], 'content' => 'a();']),
            $this->headTagManager->generateElementKey('script', ['attributes' => ['src' => self::URL]]),
            $this->headTagManager->generateElementKey('script', ['attributes' => ['src' => self::CSS]]),
        ];

        $this->assertSame($keys, array_values(array_unique($keys)));
    }

    /**
     * Text that is not valid UTF-8 still yields a key, so adding it never throws out of a template
     */
    public function testAMetaWithInvalidUtf8GetsAKey(): void
    {
        $this->headTagManager->addMetaName('description', "caf\xE9");

        $keys = array_keys($this->headTagManager->getAllElements());
        $this->assertCount(1, $keys);
        $this->assertMatchesRegularExpression('/^meta_[0-9a-f]{64}$/', $keys[0]);
    }

    /**
     * Invalid UTF-8 in content, in an attribute name or inside an array value yields a key too
     */
    public function testInvalidUtf8AnywhereInTheIdentityYieldsAKey(): void
    {
        $this->assertMatchesRegularExpression(
            '/^script_[0-9a-f]{64}$/',
            $this->headTagManager->generateElementKey('script', ['attributes' => [], 'content' => "x = '\xE9';"])
        );
        $this->assertMatchesRegularExpression(
            '/^meta_[0-9a-f]{64}$/',
            $this->headTagManager->generateElementKey('meta', ['attributes' => ["data-\xE9" => 'a']])
        );
        $this->assertMatchesRegularExpression(
            '/^link_[0-9a-f]{64}$/',
            $this->headTagManager->generateElementKey('link', ['attributes' => ['data' => ['k' => ["\xFF"]]]])
        );
    }

    /**
     * Two invalid spellings that differ in their valid text still get different keys
     */
    public function testInvalidUtf8KeepsTheRestOfTheTextInTheIdentity(): void
    {
        $this->assertNotSame(
            $this->headTagManager->generateElementKey('meta', ['attributes' => ['content' => "caf\xE9"]]),
            $this->headTagManager->generateElementKey('meta', ['attributes' => ['content' => "the\xE9"]])
        );
    }

    /**
     * A value object inside an array value compares by its text, so two with different text are two elements
     */
    public function testAStringableInsideAnArrayValueIsIdentifiedByItsText(): void
    {
        $first = ['attributes' => ['data' => ['label' => $this->stringable('a')]]];
        $second = ['attributes' => ['data' => ['label' => $this->stringable('b')]]];

        $this->assertNotSame(
            $this->headTagManager->generateElementKey('link', $first),
            $this->headTagManager->generateElementKey('link', $second)
        );
        $this->assertSame(
            $this->headTagManager->generateElementKey('link', ['attributes' => ['data' => ['label' => 'a']]]),
            $this->headTagManager->generateElementKey('link', $first)
        );
    }

    /**
     * Content compares by its text, so two value objects with different text are two elements
     */
    public function testStringableContentIsIdentifiedByItsText(): void
    {
        $untypedKey = new \ReflectionMethod($this->headTagManager, 'generateElementKey');

        $first = $untypedKey->invoke($this->headTagManager, 'script', ['content' => $this->stringable('a();')]);
        $second = $untypedKey->invoke($this->headTagManager, 'script', ['content' => $this->stringable('b();')]);

        $this->assertNotSame($first, $second);
        $this->assertSame(
            $this->headTagManager->generateElementKey('script', ['content' => 'a();']),
            $first
        );
    }

    public function testCanAddMetaWithNameAndContent(): void
    {
        $this->headTagManager->addMetaName('description', 'Test');

        $this->assertSame('<meta name="description" content="Test">' . PHP_EOL, $this->headTagManager->render());
    }

    public function testCanAddMetaWithPropertyAndContent(): void
    {
        $this->headTagManager->addMetaProperty('og:title', 'Test');

        $this->assertSame('<meta property="og:title" content="Test">' . PHP_EOL, $this->headTagManager->render());
    }

    public function testCanAddCharset(): void
    {
        $this->headTagManager->addCharset();

        $this->assertTrue($this->headTagManager->hasElement('charset'));
        $this->assertSame('<meta charset="UTF-8">' . PHP_EOL, $this->headTagManager->render());
    }

    public function testCanAddInlineScriptAndStyle(): void
    {
        $this->headTagManager->addInlineScript('console.log("test");');
        $this->headTagManager->addInlineStyle('body { color: red; }');

        $this->assertSame(
            '<style>body { color: red; }</style>' . PHP_EOL . '<script>console.log("test");</script>' . PHP_EOL,
            $this->headTagManager->render()
        );
    }

    public function testRenderSortsElementsBySortOrderKeepingInsertionOrderForEqualValues(): void
    {
        $this->headTagManager->addElement($this->element('<script src="deferred.js"></script>', 200), 'deferred');
        $this->headTagManager->addElement($this->element('<meta name="first">'), 'first');
        $this->headTagManager->addElement($this->element('<script src="critical.js"></script>', 1), 'critical');
        $this->headTagManager->addElement($this->element('<meta name="second">'), 'second');

        $this->assertSame(
            '<script src="critical.js"></script>' . PHP_EOL
            . '<meta name="first">' . PHP_EOL
            . '<meta name="second">' . PHP_EOL
            . '<script src="deferred.js"></script>' . PHP_EOL,
            $this->headTagManager->render()
        );
    }

    /**
     * One element that fails to render is logged and left out; the rest of the head is still rendered
     */
    public function testAnElementThatFailsToRenderIsLoggedAndSkipped(): void
    {
        $failure = new \InvalidArgumentException('Either remote URL or hashable content is required');
        $broken = $this->createMock(HeadElementInterface::class);
        $broken->method('getSortOrder')->willReturn(100);
        $broken->method('render')->willThrowException($failure);

        $this->headTagManager->addElement($this->element('<meta name="first">'), 'first');
        $this->headTagManager->addElement($broken, 'broken');
        $this->headTagManager->addElement($this->element('<meta name="third">'), 'third');

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('broken'),
                $this->callback(static fn (array $context): bool => $context['key'] === 'broken'
                    && $context['class'] === get_class($broken)
                    && $context['exception'] === $failure)
            );

        $this->assertSame(
            '<meta name="first">' . PHP_EOL . '<meta name="third">' . PHP_EOL,
            $this->headTagManager->render()
        );
    }

    public function testGetRenderedElementsUsesTheRenderOrderAndSkipsFailures(): void
    {
        $broken = $this->createMock(HeadElementInterface::class);
        $broken->method('getSortOrder')->willReturn(1);
        $broken->method('render')->willThrowException(new \InvalidArgumentException('no'));

        $this->headTagManager->addElement($this->element('<meta name="late">', 300), 'late');
        $this->headTagManager->addElement($broken, 'broken');
        $this->headTagManager->addElement($this->element('<meta name="early">', 5), 'early');

        $this->assertSame(
            ['early' => '<meta name="early">', 'late' => '<meta name="late">'],
            $this->headTagManager->getRenderedElements()
        );
    }

    public function testClearRemovesAllElements(): void
    {
        $this->headTagManager->addElement($this->element('<meta name="a">'), 'test-key');

        $this->assertSame($this->headTagManager, $this->headTagManager->clear());
        $this->assertFalse($this->headTagManager->hasElement('test-key'));
        $this->assertSame([], $this->headTagManager->getAllElements());
    }

    public function testGetAllElementsReturnsAllElementsInInsertionOrder(): void
    {
        $element1 = $this->element('<meta name="a">', 200);
        $element2 = $this->element('<meta name="b">', 1);

        $this->headTagManager->addElement($element1, 'key1');
        $this->headTagManager->addElement($element2, 'key2');

        $this->assertSame(['key1' => $element1, 'key2' => $element2], $this->headTagManager->getAllElements());
    }

    /**
     * Builds a value object whose text form is the given string
     *
     * @param string $text
     * @return \Stringable
     */
    private function stringable(string $text): \Stringable
    {
        return new class ($text) implements \Stringable {
            /**
             * @param string $text
             */
            public function __construct(private readonly string $text)
            {
            }

            /**
             * @inheritDoc
             */
            public function __toString(): string
            {
                return $this->text;
            }
        };
    }

    /**
     * Builds an element that renders the given HTML
     *
     * @param string $html
     * @param int $sortOrder
     * @return HeadElementInterface
     */
    private function element(string $html, int $sortOrder = 100): HeadElementInterface
    {
        $element = $this->createMock(HeadElementInterface::class);
        $element->method('render')->willReturn($html);
        $element->method('getSortOrder')->willReturn($sortOrder);

        return $element;
    }

    /**
     * Builds a registry that resolves the four element types to their real factories and elements
     *
     * @return HeadElementFactoryRegistryInterface
     */
    private function registry(): HeadElementFactoryRegistryInterface
    {
        $services = new ElementServices();
        $secure = $services->secureHtmlRenderer();
        $attributeRenderer = $services->attributeRenderer();

        $links = $this->createMock(LinkElementObjectFactory::class);
        $links->method('create')->willReturnCallback(
            static fn (): LinkElement => new LinkElement($attributeRenderer, $secure)
        );
        $metas = $this->createMock(MetaElementObjectFactory::class);
        $metas->method('create')->willReturnCallback(
            static fn (): MetaElement => new MetaElement($attributeRenderer)
        );
        $scripts = $this->createMock(ScriptElementObjectFactory::class);
        $scripts->method('create')->willReturnCallback(
            static fn (): ScriptElement => new ScriptElement($attributeRenderer, $secure)
        );
        $styles = $this->createMock(StyleElementObjectFactory::class);
        $styles->method('create')->willReturnCallback(
            static fn (): StyleElement => new StyleElement($secure)
        );

        $factories = [
            'link' => new LinkElementFactory($links),
            'meta' => new MetaElementFactory($metas),
            'script' => new ScriptElementFactory($scripts),
            'style' => new StyleElementFactory($styles),
        ];
        $registry = $this->createMock(HeadElementFactoryRegistryInterface::class);
        $registry->method('getFactoryByType')->willReturnCallback(
            static fn (string $type): ?HeadElementFactoryInterface => $factories[$type] ?? null
        );

        return $registry;
    }
}
