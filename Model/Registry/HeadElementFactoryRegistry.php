<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model\Registry;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementFactoryInterface;
use Hryvinskyi\HeadTagManager\Api\Registry\HeadElementFactoryRegistryInterface;

/**
 * Registry for managing head element factories
 * 
 * This implementation provides a registry for managing different types of head element factories
 */
class HeadElementFactoryRegistry implements HeadElementFactoryRegistryInterface
{
    /**
     * Registry of factories by element type
     * @var HeadElementFactoryInterface[]
     */
    private array $factoriesByType = [];

    /**
     * Registry of factories by class name
     * @var HeadElementFactoryInterface[]
     */
    private array $factoriesByClassName = [];

    /**
     * Mapping of class names to element types
     * @var array<string, string>
     */
    private array $classNameToTypeMap = [];

    /**
     * @param HeadElementFactoryInterface[] $factories
     */
    public function __construct(array $factories = [])
    {
        foreach ($factories as $factory) {
            $this->registerFactory($factory);
        }
    }

    /**
     * @inheritDoc
     */
    public function registerFactory(HeadElementFactoryInterface $factory): void
    {
        $elementType = $factory->getElementType();
        $className = $factory->getElementClassName();

        $this->factoriesByType[$elementType] = $factory;
        $this->factoriesByClassName[$className] = $factory;
        $this->classNameToTypeMap[$className] = $elementType;
    }

    /**
     * @inheritDoc
     */
    public function getFactoryByType(string $elementType): ?HeadElementFactoryInterface
    {
        return $this->factoriesByType[$elementType] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function getFactoryByClassName(string $className): ?HeadElementFactoryInterface
    {
        return $this->factoriesByClassName[$className] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function getElementTypeByClassName(string $className): ?string
    {
        return $this->classNameToTypeMap[$className] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function getAllElementTypes(): array
    {
        return array_keys($this->factoriesByType);
    }

    /**
     * Check if a factory is registered for the given element type
     *
     * @param string $elementType
     * @return bool
     */
    public function hasFactoryForType(string $elementType): bool
    {
        return isset($this->factoriesByType[$elementType]);
    }

    /**
     * Check if a factory is registered for the given class name
     *
     * @param string $className
     * @return bool
     */
    public function hasFactoryForClassName(string $className): bool
    {
        return isset($this->factoriesByClassName[$className]);
    }

    /**
     * Get all registered factories
     *
     * @return HeadElementFactoryInterface[]
     */
    public function getAllFactories(): array
    {
        return array_values($this->factoriesByType);
    }

    /**
     * Clear all registered factories
     *
     * @return self
     */
    public function clear(): self
    {
        $this->factoriesByType = [];
        $this->factoriesByClassName = [];
        $this->classNameToTypeMap = [];

        return $this;
    }
}