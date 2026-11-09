<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\HeadElement;

use Hryvinskyi\Base\Exception\InvalidParamException;
use Hryvinskyi\HeadTagManager\Model\HeadElement\ScriptElement;
use Hryvinskyi\HeadTagManager\Test\Unit\Model\HeadElement\Fixture\ElementServices;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-type Attributes array<string, string|int|float|bool|\Stringable|array<mixed>|null>
 */
class ScriptElementTest extends TestCase
{
    private const SECURE_MARKUP = '<script from-the-secure-renderer></script>';
    private const REMOTE = 'https://cdn.example.com/app.js';

    /**
     * @var ElementServices
     */
    private ElementServices $services;

    /**
     * @var ScriptElement
     */
    private ScriptElement $scriptElement;

    /**
     * @var list<array{0: string, 1: array<mixed>, 2: string|null, 3: bool}> the arguments of every renderTag() call
     */
    private array $secureCalls = [];

    protected function setUp(): void
    {
        $this->services = new ElementServices();
        $this->scriptElement = new ScriptElement(
            $this->services->attributeRenderer(),
            $this->services->secureHtmlRenderer()
        );
    }

    public function testCanSetAndGetContent(): void
    {
        $content = 'console.log("test");';
        $this->scriptElement->setContent($content);

        $this->assertSame($content, $this->scriptElement->getContent());
    }

    public function testRendersScriptWithContent(): void
    {
        $this->scriptElement->setContent('console.log("test");');

        $this->assertSame('<script>console.log("test");</script>', $this->scriptElement->render());
    }

    public function testRendersARemoteScriptWithAttributes(): void
    {
        $this->scriptElement->setAttributes(['src' => self::REMOTE, 'type' => 'module']);

        $this->assertSame(
            '<script type="module" src="https://cdn.example.com/app.js"></script>',
            $this->scriptElement->render()
        );
    }

    /**
     * Through the secure renderer, `false` and `null` leave the attribute out, `true` gives an empty value, numbers
     * and `Stringable`s their text
     */
    public function testRendersEveryAttributeValueTypeByOneRule(): void
    {
        $this->scriptElement->setAttributes([
            'src' => self::REMOTE,
            'async' => false,
            'nonce' => null,
            'defer' => true,
            'data-retries' => 3,
            'data-ratio' => 1.5,
            'data-label' => $this->stringable('label'),
        ]);

        $this->assertSame(
            '<script src="https://cdn.example.com/app.js" defer="" data-retries="3" data-ratio="1.5"'
            . ' data-label="label"></script>',
            $this->scriptElement->render()
        );
    }

    /**
     * Inline content always goes through the secure renderer, which adds the nonce or hash the policy asks for
     */
    public function testInlineContentGoesThroughTheSecureRenderer(): void
    {
        $element = $this->recordingElement(['src' => '/media/js/x.js'], 'run();');

        $this->assertSame(self::SECURE_MARKUP, $element->render());
        $this->assertSame([['script', ['src' => '/media/js/x.js'], 'run();', false]], $this->secureCalls);
    }

    /**
     * An external script whose source the content security processor reads a host from goes through the secure
     * renderer, which adds that host to the policy; it receives the text attributes and no content
     */
    public function testARemoteExternalScriptGoesThroughTheSecureRenderer(): void
    {
        $element = $this->recordingElement(['src' => self::REMOTE, 'defer' => true, 'data-n' => 2]);

        $this->assertSame(self::SECURE_MARKUP, $element->render());
        $this->assertSame(
            [['script', ['src' => self::REMOTE, 'defer' => '', 'data-n' => '2'], '', false]],
            $this->secureCalls
        );
    }

    /**
     * A script with no content and no source the processor reads a host from would make that processor fail, and
     * the head tag manager would leave it out of the page; it is written as text instead
     *
     * @dataProvider textScriptProvider
     * @param Attributes $attributes the attributes set
     * @param string|null $content the script content
     */
    public function testEveryOtherScriptIsWrittenAsText(array $attributes, ?string $content): void
    {
        $element = $this->recordingElement($attributes, $content);
        $expected = '<script' . $this->services->attributeRenderer()->render($element->getAttributes()) . '></script>';

        $this->assertSame($expected, $element->render());
        $this->assertSame([], $this->secureCalls);
    }

    /**
     * The scripts the secure renderer must not see
     *
     * @return array<string, array{0: Attributes, 1: string|null}>
     */
    public static function textScriptProvider(): array
    {
        return [
            'relative source' => [['src' => '/media/js/x.js'], null],
            'relative source, empty content' => [['src' => '/media/js/x.js'], ''],
            'protocol-relative source' => [['src' => '//cdn.example.com/x.js'], null],
            'upper-case scheme' => [['SRC' => 'HTTPS://cdn.example.com/x.js'], null],
            'empty host' => [['src' => 'https:///x.js'], null],
            'port the processor refuses' => [['src' => 'https://cdn.example.com:999999/x.js'], null],
            'no source and no content' => [['type' => 'module'], null],
            'remote source with an array attribute' => [['src' => self::REMOTE, 'data' => ['variant' => 'a']], null],
        ];
    }

    /**
     * The text form writes a `true` attribute in its bare boolean form and expands an array into data attributes
     */
    public function testATextScriptIsWrittenByTheAttributeRenderer(): void
    {
        $this->scriptElement->setAttributes(['src' => '/media/js/x.js', 'defer' => true, 'data' => ['id' => '7']]);

        $this->assertSame('<script src="/media/js/x.js" defer data-id="7"></script>', $this->scriptElement->render());
    }

    /**
     * An array has no text form for the secure renderer, which inline content needs; the element reports it
     * instead of rendering "Array"
     */
    public function testAnArrayAttributeValueOnAnInlineScriptFailsToRender(): void
    {
        $this->scriptElement->setAttribute('data-list', ['a', 'b']);
        $this->scriptElement->setContent('run();');

        $this->expectException(InvalidParamException::class);

        $this->scriptElement->render();
    }

    public function testConstructorTakesAttributesContentAndSortOrder(): void
    {
        $element = new ScriptElement(
            $this->services->attributeRenderer(),
            $this->services->secureHtmlRenderer(),
            ['SRC' => 'script.js'],
            'console.log("test");',
            7
        );

        $this->assertSame(['src' => 'script.js'], $element->getAttributes());
        $this->assertSame('console.log("test");', $element->getContent());
        $this->assertSame(7, $element->getSortOrder());
    }

    public function testDefaultSortOrderIs100(): void
    {
        $this->assertSame(100, $this->scriptElement->getSortOrder());
    }

    /**
     * Builds a script element whose secure renderer records every call and answers a marker
     *
     * @param Attributes $attributes
     * @param string|null $content
     * @return ScriptElement
     */
    private function recordingElement(array $attributes, ?string $content = null): ScriptElement
    {
        /** @var SecureHtmlRenderer&MockObject $secureHtmlRenderer */
        $secureHtmlRenderer = $this->createMock(SecureHtmlRenderer::class);
        $secureHtmlRenderer->method('renderTag')->willReturnCallback(
            function (string $tag, array $attributes, ?string $content = null, bool $textContent = true): string {
                $this->secureCalls[] = [$tag, $attributes, $content, $textContent];

                return self::SECURE_MARKUP;
            }
        );

        return new ScriptElement($this->services->attributeRenderer(), $secureHtmlRenderer, $attributes, $content);
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
}
