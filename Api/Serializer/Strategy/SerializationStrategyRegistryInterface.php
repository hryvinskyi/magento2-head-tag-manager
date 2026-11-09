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
 * Registry interface for managing serialization strategies
 *
 * When several strategies claim the same element class or type, the lookups return the one with the highest
 * priority.
 *
 * @api
 */
interface SerializationStrategyRegistryInterface
{
    /**
     * Register a serialization strategy
     *
     * @param HeadElementSerializationStrategyInterface $strategy the strategy to register
     * @return void
     */
    public function registerStrategy(HeadElementSerializationStrategyInterface $strategy): void;

    /**
     * Get the appropriate strategy for the given element
     *
     * @param HeadElementInterface $element the element to serialize
     * @return HeadElementSerializationStrategyInterface|null
     */
    public function getStrategyForElement(HeadElementInterface $element): ?HeadElementSerializationStrategyInterface;

    /**
     * Get strategy by element type
     *
     * @param string $elementType the element type
     * @return HeadElementSerializationStrategyInterface|null
     */
    public function getStrategyByType(string $elementType): ?HeadElementSerializationStrategyInterface;

    /**
     * Get strategy by class name
     *
     * @param string $className the element class name
     * @return HeadElementSerializationStrategyInterface|null
     */
    public function getStrategyByClassName(string $className): ?HeadElementSerializationStrategyInterface;

    /**
     * Get all registered strategies, highest priority first
     *
     * @return list<HeadElementSerializationStrategyInterface>
     */
    public function getAllStrategies(): array;

    /**
     * Check if a strategy is registered for the given element type
     *
     * @param string $elementType the element type
     * @return bool
     */
    public function hasStrategyForType(string $elementType): bool;

    /**
     * Clear all registered strategies
     *
     * @return void
     */
    public function clear(): void;
}
