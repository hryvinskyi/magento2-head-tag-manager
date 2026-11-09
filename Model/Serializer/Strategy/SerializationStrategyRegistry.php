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
 *
 * When several strategies claim the same element class or element type, the one with the highest priority is
 * the one the lookups return; among equal priorities the one registered last wins, so a custom strategy with the
 * default priority replaces a built-in one.
 */
class SerializationStrategyRegistry implements SerializationStrategyRegistryInterface
{
    /**
     * @var array<string, HeadElementSerializationStrategyInterface>
     */
    private array $strategiesByType = [];

    /**
     * @var array<string, HeadElementSerializationStrategyInterface>
     */
    private array $strategiesByClassName = [];

    /**
     * Every registered strategy, highest priority first
     *
     * @var list<HeadElementSerializationStrategyInterface>
     */
    private array $allStrategies = [];

    /**
     * Strategies found through canHandle(), by the class of the element they were asked about
     *
     * @var array<string, HeadElementSerializationStrategyInterface>
     */
    private array $resolvedByElementClass = [];

    /**
     * @param array<int|string,HeadElementSerializationStrategyInterface> $strategies
     */
    public function __construct(array $strategies = [])
    {
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

        if ($this->outranks($strategy, $this->strategiesByType[$elementType] ?? null)) {
            $this->strategiesByType[$elementType] = $strategy;
        }
        if ($this->outranks($strategy, $this->strategiesByClassName[$className] ?? null)) {
            $this->strategiesByClassName[$className] = $strategy;
        }

        // Placed ahead of the strategies it ties with: the sort is stable, so the latest registered ranks first
        array_unshift($this->allStrategies, $strategy);
        usort(
            $this->allStrategies,
            static fn (
                HeadElementSerializationStrategyInterface $a,
                HeadElementSerializationStrategyInterface $b
            ): int => $b->getPriority() <=> $a->getPriority()
        );
        $this->resolvedByElementClass = [];
    }

    /**
     * @inheritDoc
     */
    public function getStrategyForElement(HeadElementInterface $element): ?HeadElementSerializationStrategyInterface
    {
        $className = get_class($element);
        if (isset($this->strategiesByClassName[$className])) {
            return $this->strategiesByClassName[$className];
        }

        if (isset($this->resolvedByElementClass[$className])) {
            return $this->resolvedByElementClass[$className];
        }

        foreach ($this->allStrategies as $strategy) {
            if ($strategy->canHandle($element)) {
                $this->resolvedByElementClass[$className] = $strategy;
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
        $this->resolvedByElementClass = [];
    }

    /**
     * Tell whether a strategy takes the place of the one stored for the same key
     *
     * @param HeadElementSerializationStrategyInterface $candidate the strategy being registered
     * @param HeadElementSerializationStrategyInterface|null $stored the strategy already stored, if any
     * @return bool true when nothing is stored or the candidate's priority is higher or equal
     */
    private function outranks(
        HeadElementSerializationStrategyInterface $candidate,
        ?HeadElementSerializationStrategyInterface $stored
    ): bool {
        return $stored === null || $candidate->getPriority() >= $stored->getPriority();
    }
}
