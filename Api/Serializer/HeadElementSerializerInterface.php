<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Api\Serializer;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;

interface HeadElementSerializerInterface
{
    /**
     * Serialize elements to array
     *
     * @param HeadElementInterface[] $elements
     * @return array
     */
    public function serialize(array $elements): array;

    /**
     * Unserialize elements from array
     *
     * @param array $data
     * @return HeadElementInterface[]
     */
    public function unserialize(array $data): array;
}