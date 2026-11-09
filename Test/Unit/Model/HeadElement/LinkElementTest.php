<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\HeadElement;

use Hryvinskyi\Base\Exception\InvalidParamException;
use Hryvinskyi\HeadTagManager\Model\HeadElement\LinkElement;
use Hryvinskyi\HeadTagManager\Test\Unit\Model\HeadElement\Fixture\ElementServices;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-type Attributes array<string, string|int|float|bool|\Stringable|array<mixed>|null>
 */
class LinkElementTest extends TestCase
{
    private const SECURE_MARKUP = '<link from-the-secure-renderer/>';

    /**
     * @var ElementServices
     */
    private ElementServices $services;

    /**
     * @var SecureHtmlRenderer&MockObject
     */
    private MockObject $secureHtmlRenderer;

    /**
     * @var list<array{0: string, 1: array<mixed>, 2: string|null, 3: bool}> the arguments of every renderTag() call
     */
    private array $secureCalls = [];

    /**
     * @var LinkElement
     */
    private LinkElement $linkElement;

    protected function setUp(): void
    {
        $this->services = new ElementServices();
        $this->secureHtmlRenderer = $this->createMock(SecureHtmlRenderer::class);
        $this->secureHtmlRenderer->method('renderTag')->willReturnCallback(
            function (string $tag, array $attributes, ?string $content = null, bool $textContent = true): string {
                $this->secureCalls[] = [$tag, $attributes, $content, $textContent];

                return self::SECURE_MARKUP;
            }
        );
        $this->linkElement = new LinkElement($this->services->attributeRenderer(), $this->secureHtmlRenderer);
    }

    public function testRendersLinkElementWithAttributes(): void
    {
        $this->linkElement->setAttributes([
            'rel' => 'stylesheet',
            'href' => 'styles.css'
        ]);

        /** @noinspection HtmlUnknownTarget */
        $this->assertSame(
            '<link href="styles.css" rel="stylesheet">',
            $this->linkElement->render()
        );
    }

    /**
     * `true` gives the boolean form of an attribute, `false` and `null` leave it out
     */
    public function testRendersBooleanAndNullAttributeValues(): void
    {
        $this->linkElement->setAttributes([
            'rel' => 'preconnect',
            'href' => 'https://cdn.example.com',
            'crossorigin' => true,
            'disabled' => false,
            'media' => null,
        ]);

        $this->assertSame(
            '<link href="https://cdn.example.com" rel="preconnect" crossorigin>',
            $this->linkElement->render()
        );
    }

    /**
     * An array value is expanded by the attribute renderer, including keys the object manager would interpret
     */
    public function testRendersADataArrayAsDataAttributes(): void
    {
        $this->linkElement->setAttributes(['rel' => 'preload', 'data' => ['instance' => 'hero', 'argument' => 'x']]);

        $this->assertSame('<link rel="preload" data-instance="hero" data-argument="x">', $this->linkElement->render());
    }

    public function testRendersEmptyLinkElementWithNoAttributes(): void
    {
        $this->assertSame('<link>', $this->linkElement->render());
    }

    public function testProperlyEscapesAttributeValues(): void
    {
        $this->linkElement->setAttribute('href', 'test.css?param="dangerous"');

        $this->assertStringContainsString('href="test.css?param=&quot;dangerous&quot;"', $this->linkElement->render());
    }

    public function testRendersWithoutClosingSlashInThePreferredAttributeOrder(): void
    {
        $this->linkElement->setAttributes([
            'href' => '/f.woff2',
            'as' => 'font',
            'rel' => 'preload',
            'type' => 'font/woff2'
        ]);

        /** @noinspection HtmlUnknownTarget */
        $this->assertSame(
            '<link type="font/woff2" href="/f.woff2" rel="preload" as="font">',
            $this->linkElement->render()
        );
    }

    /**
     * A link that loads a remote stylesheet is built by the secure renderer, whose processors add its host to the
     * content security policy; the renderer receives the text attributes in the order they were set
     *
     * @dataProvider remoteStylesheetProvider
     * @param Attributes $attributes the attributes set
     * @param array<string, string> $expected the text attributes the secure renderer receives
     */
    public function testARemoteStylesheetIsRenderedByTheSecureRenderer(array $attributes, array $expected): void
    {
        $this->linkElement->setAttributes($attributes);

        $this->assertSame(self::SECURE_MARKUP, $this->linkElement->render());
        $this->assertSame([['link', $expected, null, false]], $this->secureCalls);
    }

    /**
     * The links whose host belongs in the policy's style directive
     *
     * @return array<string, array{0: Attributes, 1: array<string, string>}>
     */
    public static function remoteStylesheetProvider(): array
    {
        return [
            'stylesheet over https' => [
                ['rel' => 'stylesheet', 'href' => 'https://fonts.example.com/a.css'],
                ['rel' => 'stylesheet', 'href' => 'https://fonts.example.com/a.css'],
            ],
            'stylesheet over http' => [
                ['href' => 'http://cdn.example.com/b.css', 'rel' => 'stylesheet'],
                ['href' => 'http://cdn.example.com/b.css', 'rel' => 'stylesheet'],
            ],
            'preload as style, boolean and empty values' => [
                [
                    'rel' => 'preload',
                    'as' => 'style',
                    'href' => 'https://cdn.example.com/s.css',
                    'crossorigin' => true,
                    'disabled' => false,
                    'media' => null,
                    'data-weight' => 3,
                ],
                [
                    'rel' => 'preload',
                    'as' => 'style',
                    'href' => 'https://cdn.example.com/s.css',
                    'crossorigin' => '',
                    'data-weight' => '3',
                ],
            ],
            'upper-case names and a mixed-case rel' => [
                ['REL' => 'StyleSheet', 'HREF' => 'https://fonts.example.com/c.css'],
                ['rel' => 'StyleSheet', 'href' => 'https://fonts.example.com/c.css'],
            ],
            'stylesheet among other rel tokens' => [
                ['rel' => "alternate\tstylesheet", 'href' => 'https://cdn.example.com/d.css', 'title' => 'High'],
                ['rel' => "alternate\tstylesheet", 'href' => 'https://cdn.example.com/d.css', 'title' => 'High'],
            ],
            'user information and a port' => [
                ['rel' => 'stylesheet', 'href' => 'https://user@cdn.example.com:8443/f.css'],
                ['rel' => 'stylesheet', 'href' => 'https://user@cdn.example.com:8443/f.css'],
            ],
            'preload with a mixed-case as' => [
                ['rel' => 'PRELOAD', 'as' => 'Style', 'href' => 'https://cdn.example.com/e.css'],
                ['rel' => 'PRELOAD', 'as' => 'Style', 'href' => 'https://cdn.example.com/e.css'],
            ],
        ];
    }

    /**
     * Every other link is built as text by the attribute renderer: the secure renderer's processor would either
     * file its host under the wrong directive or fail on a link it cannot read a host from
     *
     * @dataProvider textLinkProvider
     * @param Attributes $attributes the attributes set
     */
    public function testEveryOtherLinkIsRenderedAsText(array $attributes): void
    {
        $this->linkElement->setAttributes($attributes);
        $expected = '<link' . $this->services->attributeRenderer()->render($this->linkElement->getAttributes()) . '>';

        $this->assertSame($expected, $this->linkElement->render());
        $this->assertSame([], $this->secureCalls);
    }

    /**
     * The links the secure renderer must not see
     *
     * @return array<string, array{0: Attributes}>
     */
    public static function textLinkProvider(): array
    {
        return [
            'relative stylesheet' => [['rel' => 'stylesheet', 'href' => '/css/a.css']],
            'protocol-relative stylesheet' => [['rel' => 'stylesheet', 'href' => '//cdn.example.com/a.css']],
            'remote icon' => [['rel' => 'icon', 'href' => 'https://cdn.example.com/favicon.ico']],
            'remote font preload' => [
                ['rel' => 'preload', 'as' => 'font', 'href' => 'https://cdn.example.com/f.woff2'],
            ],
            'remote preload without as' => [['rel' => 'preload', 'href' => 'https://cdn.example.com/g.css']],
            'upper-case scheme' => [['rel' => 'stylesheet', 'HREF' => 'HTTPS://upper.example.com/b.css']],
            'mixed-case scheme' => [['rel' => 'stylesheet', 'href' => 'Https://mixed.example.com/b.css']],
            'no host' => [['rel' => 'stylesheet', 'href' => 'https:///a.css']],
            'query right after the scheme' => [['rel' => 'stylesheet', 'href' => 'https://?a.css']],
            'stylesheet that is only a word inside a token' => [
                ['rel' => 'stylesheets', 'href' => 'https://cdn.example.com/h.css'],
            ],
            'remote stylesheet with an array attribute' => [
                ['rel' => 'stylesheet', 'href' => 'https://cdn.example.com/i.css', 'data' => ['variant' => 'hero']],
            ],
            'host starting with a port' => [['rel' => 'stylesheet', 'href' => 'https://:443/a.css']],
            'empty host after user information' => [['rel' => 'stylesheet', 'href' => 'https://@/a.css']],
            'port the processor refuses' => [['rel' => 'stylesheet', 'href' => 'https://cdn.example.com:999999/']],
            'no href' => [['rel' => 'stylesheet']],
            'no rel' => [['href' => 'https://cdn.example.com/j.css']],
        ];
    }

    /**
     * Behind the secure renderer, the HTML renderer an install uses closes the void tag with ` />` and keeps the
     * attribute order and escaping of the text path
     */
    public function testARemoteStylesheetRendersAsASelfClosedTag(): void
    {
        $element = new LinkElement($this->services->attributeRenderer(), $this->services->secureHtmlRenderer());
        $element->setAttributes(['rel' => 'stylesheet', 'href' => 'https://fonts.example.com/a.css']);

        $this->assertSame(
            '<link href="https://fonts.example.com/a.css" rel="stylesheet" />',
            $element->render()
        );
    }

    /**
     * A value no static type reaches, as the object manager passes it from configuration, fails as the attribute
     * renderer reports it rather than reaching the secure renderer
     */
    public function testAValueWithoutATextFormFailsInTheAttributeRenderer(): void
    {
        $this->linkElement->setAttributes(['rel' => 'stylesheet', 'href' => 'https://cdn.example.com/k.css']);
        $attributes = new \ReflectionProperty(LinkElement::class, 'attributes');
        $attributes->setValue($this->linkElement, $this->linkElement->getAttributes() + ['data' => [new \stdClass()]]);

        try {
            $this->linkElement->render();
            $this->fail('A value without a text form was rendered.');
        } catch (InvalidParamException) {
            $this->assertSame([], $this->secureCalls);
        }
    }

    /**
     * Upper-case names passed to the constructor are stored as the setter stores them, so a remote stylesheet built
     * that way still reaches the secure renderer
     */
    public function testConstructorAttributeNamesFollowTheSetterRules(): void
    {
        $element = new LinkElement(
            $this->services->attributeRenderer(),
            $this->secureHtmlRenderer,
            ['HREF' => 'https://fonts.example.com/a.css', 'REL' => 'stylesheet']
        );

        $this->assertSame('https://fonts.example.com/a.css', $element->getAttribute('href'));
        $this->assertSame(self::SECURE_MARKUP, $element->render());
        $this->assertSame(
            [['link', ['href' => 'https://fonts.example.com/a.css', 'rel' => 'stylesheet'], null, false]],
            $this->secureCalls
        );
    }

    public function testConstructorTakesAttributesAndSortOrder(): void
    {
        $element = new LinkElement(
            $this->services->attributeRenderer(),
            $this->secureHtmlRenderer,
            ['rel' => 'icon', 'href' => '/favicon.ico'],
            7
        );

        $this->assertSame(['rel' => 'icon', 'href' => '/favicon.ico'], $element->getAttributes());
        $this->assertSame(7, $element->getSortOrder());
    }
}
