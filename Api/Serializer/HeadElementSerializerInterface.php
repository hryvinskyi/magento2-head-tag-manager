<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Api\Serializer;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;

/**
 * Turns head elements into plain data a cache can store, and back
 *
 * @api
 */
interface HeadElementSerializerInterface
{
    /**
     * Serialize elements to array
     *
     * @param array<int|string,HeadElementInterface> $elements the elements by key
     * @return array<int|string, array<string, mixed>> the data of each element that could be serialized, by key
     */
    public function serialize(array $elements): array;

    /**
     * Unserialize elements from array
     *
     * @param array<int|string,mixed> $data the data of each element by key, as serialize() returned it
     * @return array<string, HeadElementInterface> the elements that could be recreated, by key
     */
    public function unserialize(array $data): array;
}
