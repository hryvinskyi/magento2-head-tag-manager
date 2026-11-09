<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Api\Serializer\Strategy;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;

/**
 * Strategy interface for serializing specific types of head elements
 *
 * This interface follows the Strategy pattern, allowing different serialization
 * logic for different element types without using instanceof checks.
 *
 * @api
 */
interface HeadElementSerializationStrategyInterface
{
    /**
     * Check if this strategy can handle the given element
     *
     * @param HeadElementInterface $element the element to check
     * @return bool
     */
    public function canHandle(HeadElementInterface $element): bool;

    /**
     * Serialize the element to an array
     *
     * @param HeadElementInterface $element the element to serialize
     * @param string $key Element key
     * @return array<string, mixed> Serialized element data
     */
    public function serialize(HeadElementInterface $element, string $key): array;

    /**
     * Get the element type this strategy handles
     *
     * @return string
     */
    public function getElementType(): string;

    /**
     * Get the element class name this strategy handles
     *
     * @return string
     */
    public function getElementClassName(): string;

    /**
     * Get priority for this strategy
     *
     * A higher number means a higher priority. When several strategies claim the same element class or type, the
     * registry's lookups return the one with the highest priority.
     *
     * @return int
     */
    public function getPriority(): int;
}
