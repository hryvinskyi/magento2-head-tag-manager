<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Api\Serializer;

/**
 * Puts an element's attributes in a form that survives encoding and decoding
 *
 * A serialization strategy extending the module's abstract strategy receives it through its constructor.
 *
 * @api
 */
interface StorableAttributesInterface
{
    /**
     * Replace every \Stringable value, at any depth, with its text
     *
     * An encoder would write a \Stringable object's public properties instead of its text, and the decoded entry
     * would not give the attribute back. Every other value, and every key, is kept as it is.
     *
     * @param array<string,mixed> $attributes attribute name => value
     * @return array<string,mixed> attribute name => value, with no \Stringable left in it
     */
    public function fromAttributes(array $attributes): array;
}
