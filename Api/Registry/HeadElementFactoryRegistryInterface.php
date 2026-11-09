<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Api\Registry;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementFactoryInterface;

/**
 * Finds the factory that creates a head element, by element type or by element class
 *
 * @api
 */
interface HeadElementFactoryRegistryInterface
{
    /**
     * Register element factory
     *
     * @param HeadElementFactoryInterface $factory the factory to register
     * @return void
     */
    public function registerFactory(HeadElementFactoryInterface $factory): void;

    /**
     * Get factory by element type
     *
     * @param string $elementType the element type
     * @return HeadElementFactoryInterface|null
     */
    public function getFactoryByType(string $elementType): ?HeadElementFactoryInterface;

    /**
     * Get factory by class name
     *
     * @param string $className the element class name
     * @return HeadElementFactoryInterface|null
     */
    public function getFactoryByClassName(string $className): ?HeadElementFactoryInterface;

    /**
     * Get element type by class name
     *
     * @param string $className the element class name
     * @return string|null
     */
    public function getElementTypeByClassName(string $className): ?string;

    /**
     * Get all registered element types
     *
     * @return list<string>
     */
    public function getAllElementTypes(): array;
}
