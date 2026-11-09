<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\Serializer;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Test\Unit\Model\Cache\Fixture\RealHeadElements;
use PHPUnit\Framework\TestCase;

/**
 * An attribute value that is a \Stringable, however deep in an array, comes back from the cache as its text
 */
class StringableAttributeRoundTripTest extends TestCase
{
    use RealHeadElements;

    public function testALinkWithAStrategyKeepsAStringableNestedInAnArrayAttribute(): void
    {
        $link = $this->elementRegistry()->getFactoryByType('link')?->create([
            'attributes' => [
                'rel' => 'stylesheet',
                'href' => $this->stringable('/styles.css'),
                'data' => ['label' => $this->stringable('Hero'), 'deep' => ['url' => $this->stringable('/a')]],
            ],
        ]);
        $this->assertNotNull($link);

        $restored = $this->roundTrip($link);

        $this->assertSame(
            [
                'rel' => 'stylesheet',
                'href' => '/styles.css',
                'data' => ['label' => 'Hero', 'deep' => ['url' => '/a']],
            ],
            $restored->getAttributes()
        );
    }

    public function testACustomElementWithoutAStrategyKeepsAStringableNestedInAnArrayAttribute(): void
    {
        $custom = $this->elementRegistry()->getFactoryByType('custom')?->create([
            'attributes' => ['data' => ['label' => $this->stringable('Hero')], 'name' => $this->stringable('n')],
        ]);
        $this->assertNotNull($custom);

        $restored = $this->roundTrip($custom);

        $this->assertSame(['data' => ['label' => 'Hero'], 'name' => 'n'], $restored->getAttributes());
    }

    /**
     * Serialize an element, pass the data through JSON as a cache entry does, and recreate it
     *
     * @param HeadElementInterface $element the element to store and restore
     * @return HeadElementInterface the restored element
     */
    private function roundTrip(HeadElementInterface $element): HeadElementInterface
    {
        $serializer = $this->elementSerializer();
        $stored = json_decode(json_encode($serializer->serialize(['k' => $element]), JSON_THROW_ON_ERROR), true);
        $this->assertIsArray($stored);

        $restored = $serializer->unserialize($stored);
        $this->assertArrayHasKey('k', $restored);

        return $restored['k'];
    }

    /**
     * Build a Stringable that is not JsonSerializable, so an encoder would write its public properties
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
