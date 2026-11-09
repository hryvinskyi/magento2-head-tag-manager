<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\Serializer;

use Hryvinskyi\HeadTagManager\Model\Serializer\StorableAttributes;
use PHPUnit\Framework\TestCase;

class StorableAttributesTest extends TestCase
{
    /**
     * @var StorableAttributes
     */
    private StorableAttributes $storableAttributes;

    protected function setUp(): void
    {
        $this->storableAttributes = new StorableAttributes();
    }

    public function testScalarsNullAndPlainArraysAreKept(): void
    {
        $attributes = [
            'href' => '/styles.css',
            'count' => 3,
            'ratio' => 1.5,
            'async' => true,
            'nothing' => null,
            'data' => ['variant' => 'hero', 'sizes' => [1, 2]],
        ];

        $this->assertSame($attributes, $this->storableAttributes->fromAttributes($attributes));
    }

    public function testATopLevelStringableBecomesItsText(): void
    {
        $result = $this->storableAttributes->fromAttributes(['href' => $this->stringable('/styles.css')]);

        $this->assertSame(['href' => '/styles.css'], $result);
    }

    public function testAStringableNestedInAnArrayBecomesItsText(): void
    {
        $result = $this->storableAttributes->fromAttributes([
            'data' => [
                'label' => $this->stringable('Hero'),
                'deep' => ['url' => $this->stringable('/a'), 'list' => [$this->stringable('x'), 'y']],
            ],
        ]);

        $this->assertSame(
            ['data' => ['label' => 'Hero', 'deep' => ['url' => '/a', 'list' => ['x', 'y']]]],
            $result
        );
    }

    public function testTheKeysOfNestedArraysAreKept(): void
    {
        $result = $this->storableAttributes->fromAttributes(['data' => [5 => $this->stringable('a'), 'k' => 'b']]);

        $this->assertSame(['data' => [5 => 'a', 'k' => 'b']], $result);
    }

    /**
     * Build a Stringable that is not JsonSerializable, as an encoder would write its public properties
     *
     * @param string $text the text it converts to
     * @return \Stringable
     */
    private function stringable(string $text): \Stringable
    {
        return new class ($text) implements \Stringable {
            /**
             * @param string $text
             */
            public function __construct(public readonly string $text)
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
