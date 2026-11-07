<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model\Serializer\Strategy;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Api\Registry\HeadElementFactoryRegistryInterface;
use Hryvinskyi\HeadTagManager\Api\Serializer\Strategy\HeadElementSerializationStrategyInterface;

/**
 * Abstract base class for element serialization strategies
 */
abstract class AbstractSerializationStrategy implements HeadElementSerializationStrategyInterface
{
    /**
     * @param HeadElementFactoryRegistryInterface $factoryRegistry finds the factory that recreates an element
     */
    public function __construct(
        protected readonly HeadElementFactoryRegistryInterface $factoryRegistry
    ) {
    }

    /**
     * @inheritDoc
     */
    public function canHandle(HeadElementInterface $element): bool
    {
        return is_a($element, $this->getElementClassName());
    }

    /**
     * @inheritDoc
     *
     * @return array<string, mixed>
     */
    public function serialize(HeadElementInterface $element, string $key): array
    {
        $data = [
            'type' => get_class($element),
            'short_type' => $this->getElementType(),
            'attributes' => $element->getAttributes(),
            'sort_order' => $element->getSortOrder(),
        ];

        // Add additional data specific to this element type
        $additionalData = $this->getAdditionalSerializationData($element);
        if (!empty($additionalData)) {
            $data = array_merge($data, $additionalData);
        }

        return $data;
    }

    /**
     * Get additional serialization data specific to the element type
     *
     * Override in child classes to add element-specific data.
     *
     * @param HeadElementInterface $element
     * @return array<string, mixed>
     */
    protected function getAdditionalSerializationData(HeadElementInterface $element): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getPriority(): int
    {
        return 100; // Default priority
    }
}
