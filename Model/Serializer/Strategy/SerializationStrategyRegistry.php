<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model\Serializer\Strategy;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Api\Serializer\Strategy\HeadElementSerializationStrategyInterface;
use Hryvinskyi\HeadTagManager\Api\Serializer\Strategy\SerializationStrategyRegistryInterface;

/**
 * Registry for managing serialization strategies
 */
class SerializationStrategyRegistry implements SerializationStrategyRegistryInterface
{
    /**
     * @var HeadElementSerializationStrategyInterface[]
     */
    private array $strategiesByType = [];

    /**
     * @var HeadElementSerializationStrategyInterface[]
     */
    private array $strategiesByClassName = [];

    /**
     * @var HeadElementSerializationStrategyInterface[]
     */
    private array $allStrategies = [];

    /**
     * @param HeadElementSerializationStrategyInterface[] $strategies
     */
    public function __construct(array $strategies = [])
    {
        // Sort strategies by priority (highest first)
        usort($strategies, static function ($a, $b) {
            return $b->getPriority() <=> $a->getPriority();
        });

        foreach ($strategies as $strategy) {
            $this->registerStrategy($strategy);
        }
    }

    /**
     * @inheritDoc
     */
    public function registerStrategy(HeadElementSerializationStrategyInterface $strategy): void
    {
        $elementType = $strategy->getElementType();
        $className = $strategy->getElementClassName();

        $this->strategiesByType[$elementType] = $strategy;
        $this->strategiesByClassName[$className] = $strategy;
        $this->allStrategies[] = $strategy;
    }

    /**
     * @inheritDoc
     */
    public function getStrategyForElement(HeadElementInterface $element): ?HeadElementSerializationStrategyInterface
    {
        // First try to find by exact class name
        $className = get_class($element);
        if (isset($this->strategiesByClassName[$className])) {
            return $this->strategiesByClassName[$className];
        }

        // Fallback: iterate through all strategies and find one that can handle this element
        foreach ($this->allStrategies as $strategy) {
            if ($strategy->canHandle($element)) {
                // Cache the result for future use
                $this->strategiesByClassName[$className] = $strategy;
                return $strategy;
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function getStrategyByType(string $elementType): ?HeadElementSerializationStrategyInterface
    {
        return $this->strategiesByType[$elementType] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function getStrategyByClassName(string $className): ?HeadElementSerializationStrategyInterface
    {
        return $this->strategiesByClassName[$className] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function getAllStrategies(): array
    {
        return $this->allStrategies;
    }

    /**
     * @inheritDoc
     */
    public function hasStrategyForType(string $elementType): bool
    {
        return isset($this->strategiesByType[$elementType]);
    }

    /**
     * @inheritDoc
     */
    public function clear(): void
    {
        $this->strategiesByType = [];
        $this->strategiesByClassName = [];
        $this->allStrategies = [];
    }
}