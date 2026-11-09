<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\HeadElement;

use Hryvinskyi\Base\Exception\InvalidParamException;
use Hryvinskyi\HeadTagManager\Model\HeadElement\AbstractHeadElement;
use PHPUnit\Framework\TestCase;

class AbstractHeadElementTest extends TestCase
{
    /**
     * @var AbstractHeadElement
     */
    private AbstractHeadElement $abstractHeadElement;

    protected function setUp(): void
    {
        $this->abstractHeadElement = $this->element();
    }

    /**
     * Builds the smallest concrete element, the way a third party extends the abstract class: the base class takes
     * no renderer, so the element writes its text attributes itself
     *
     * @param array<string, string|int|float|bool|\Stringable|array<mixed>|null> $attributes
     * @param int $sortOrder
     * @return AbstractHeadElement
     */
    private function element(array $attributes = [], int $sortOrder = 100): AbstractHeadElement
    {
        return new class ($attributes, $sortOrder) extends AbstractHeadElement {
            /**
             * @inheritDoc
             */
            public function render(): string
            {
                $html = '<element';
                foreach ($this->getTextAttributes() as $name => $text) {
                    $html .= ' ' . $name . '="' . $text . '"';
                }

                return $html . '>';
            }
        };
    }

    /**
     * `false` and `null` leave the attribute out, `true` gives an empty value, numbers and `Stringable`s their text,
     * in the order the attributes were set
     */
    public function testTextAttributesFollowOneRuleForEveryValueType(): void
    {
        $element = $this->element([
            'id' => 'test-id',
            'hidden' => false,
            'title' => null,
            'async' => true,
            'data-retries' => 3,
            'data-ratio' => 1.5,
            'data-label' => new class implements \Stringable {
                /**
                 * @inheritDoc
                 */
                public function __toString(): string
                {
                    return 'label';
                }
            },
        ]);

        $this->assertSame(
            '<element id="test-id" async="" data-retries="3" data-ratio="1.5" data-label="label">',
            $element->render()
        );
    }

    /**
     * An array has no text form, and neither has a value no static type reaches, as the object manager passes it
     * from configuration
     */
    public function testAnAttributeWithoutATextFormFailsToBecomeText(): void
    {
        $element = $this->element();
        $attributes = new \ReflectionProperty(AbstractHeadElement::class, 'attributes');
        $attributes->setValue($element, ['data' => [new \stdClass()]]);

        $this->expectException(InvalidParamException::class);

        $element->render();
    }

    public function testConstructorTakesAttributesAndSortOrder(): void
    {
        $element = $this->element(['id' => 'test-id'], 42);

        $this->assertSame(['id' => 'test-id'], $element->getAttributes());
        $this->assertSame(42, $element->getSortOrder());
    }

    /**
     * Names passed to the constructor follow the setter: they are stored in lower case, so the lookups find them
     */
    public function testConstructorStoresAttributeNamesInLowerCase(): void
    {
        $element = $this->element(['HREF' => '/a.css', 'REL' => 'stylesheet']);

        $this->assertSame(['href' => '/a.css', 'rel' => 'stylesheet'], $element->getAttributes());
        $this->assertSame('/a.css', $element->getAttribute('href'));
        $this->assertTrue($element->hasAttribute('rel'));
    }

    /**
     * A URL counts as remote exactly when the content security processor reads a host from it: a lower-case
     * `http://` or `https://`, optional user information, a host, an optional port it accepts, then a path, a query,
     * a fragment or the end
     *
     * @dataProvider remoteUrlProvider
     * @param string $url the URL checked
     * @param bool $expected whether the processor reads a host from it
     */
    public function testRecognisesTheUrlsTheProcessorReadsAHostFrom(string $url, bool $expected): void
    {
        $element = new class extends AbstractHeadElement {
            /**
             * @inheritDoc
             */
            public function render(): string
            {
                return '';
            }

            /**
             * Expose the check to the test
             *
             * @param string $url
             * @return bool
             */
            public function isRemote(string $url): bool
            {
                return $this->pointsAtARemoteHost($url);
            }
        };

        $this->assertSame($expected, $element->isRemote($url));
    }

    /**
     * URLs and whether the processor reads a host from them
     *
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function remoteUrlProvider(): array
    {
        return [
            'https with a path' => ['https://cdn.example.com/a.css', true],
            'http, host only' => ['http://cdn.example.com', true],
            'host and a slash' => ['http://cdn.example.com/', true],
            'host and a query' => ['https://cdn.example.com?v=1', true],
            'host and a fragment' => ['https://cdn.example.com#top', true],
            'user information' => ['https://user:secret@cdn.example.com/a.css', true],
            'user and a port, no path' => ['https://user@cdn.example.com:80', true],
            'the highest port' => ['https://cdn.example.com:65535/a.css', true],
            'port zero' => ['https://cdn.example.com:0/a.css', true],
            'relative' => ['/media/js/x.js', false],
            'protocol-relative' => ['//cdn.example.com/x.js', false],
            'upper-case scheme' => ['HTTPS://cdn.example.com/x.js', false],
            'mixed-case scheme' => ['Https://cdn.example.com/x.js', false],
            'other scheme' => ['ftp://cdn.example.com/x.js', false],
            'empty' => ['', false],
            'empty host' => ['https:///a.css', false],
            'query right after the scheme' => ['https://?a.css', false],
            'host starting with a port' => ['https://:443/a.css', false],
            'empty host after user information' => ['https://@/a.css', false],
            'port of six digits' => ['https://cdn.example.com:999999/', false],
            'port above the highest' => ['https://cdn.example.com:99999/', false],
            'port with a letter' => ['https://cdn.example.com:8a/', false],
            'one slash' => ['https:/cdn.example.com/a', false],
            'space in the host' => ['https://cdn example.com/a', false],
            'trailing line break' => ["https://cdn.example.com\n", false],
        ];
    }

    public function testGetAttributeReturnsANonStringValueAsItWasSet(): void
    {
        $this->abstractHeadElement->setAttribute('crossorigin', true);
        $this->abstractHeadElement->setAttribute('data-retries', 3);

        $this->assertTrue($this->abstractHeadElement->getAttribute('crossorigin'));
        $this->assertSame(3, $this->abstractHeadElement->getAttribute('data-retries'));
    }

    public function testAttributeNamesAreStoredInLowerCase(): void
    {
        $this->abstractHeadElement->setAttributes(['SRC' => '/a.js', 'Data-Id' => '7']);

        $this->assertSame(['src' => '/a.js', 'data-id' => '7'], $this->abstractHeadElement->getAttributes());
        $this->assertSame('/a.js', $this->abstractHeadElement->getAttribute('Src'));
        $this->assertTrue($this->abstractHeadElement->hasAttribute('DATA-ID'));

        $this->abstractHeadElement->removeAttribute('SRC');
        $this->assertFalse($this->abstractHeadElement->hasAttribute('src'));
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
}
