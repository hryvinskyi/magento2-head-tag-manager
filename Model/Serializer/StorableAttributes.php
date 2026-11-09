<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model\Serializer;

use Hryvinskyi\HeadTagManager\Api\Serializer\StorableAttributesInterface;

/**
 * Converts the \Stringable values of an element's attributes, however deep in an array, to their text
 */
class StorableAttributes implements StorableAttributesInterface
{
    /**
     * @inheritDoc
     */
    public function fromAttributes(array $attributes): array
    {
        $storable = [];
        foreach ($attributes as $name => $value) {
            $storable[$name] = $this->convert($value);
        }

        return $storable;
    }

    /**
     * Convert a value: a \Stringable becomes its text and an array has its own values converted, keys kept
     *
     * @param mixed $value the value to convert
     * @return mixed the converted value
     */
    private function convert(mixed $value): mixed
    {
        return match (true) {
            $value instanceof \Stringable => (string)$value,
            is_array($value) => array_map($this->convert(...), $value),
            default => $value,
        };
    }
}
