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
 */
interface HeadElementSerializationStrategyInterface
{
    /**
     * Check if this strategy can handle the given element
     *
     * @param HeadElementInterface $element
     * @return bool
     */
    public function canHandle(HeadElementInterface $element): bool;

    /**
     * Serialize the element to an array
     *
     * @param HeadElementInterface $element
     * @param string $key Element key
     * @return array Serialized element data
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
     * Get priority for this strategy (higher number = higher priority)
     * Useful when multiple strategies can handle the same element
     *
     * @return int
     */
    public function getPriority(): int;
}