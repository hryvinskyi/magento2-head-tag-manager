<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model\Serializer;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Api\Registry\HeadElementFactoryRegistryInterface;
use Hryvinskyi\HeadTagManager\Api\Serializer\HeadElementSerializerInterface;
use Hryvinskyi\HeadTagManager\Api\Serializer\Strategy\SerializationStrategyRegistryInterface;
use Psr\Log\LoggerInterface;

/**
 * Default serializer for head elements using factory registry and strategy pattern
 */
class HeadElementSerializer implements HeadElementSerializerInterface
{
    /**
     * @param HeadElementFactoryRegistryInterface $factoryRegistry finds the factory that recreates an element
     * @param SerializationStrategyRegistryInterface $strategyRegistry finds the strategy that serializes an element
     * @param LoggerInterface $logger records an element that cannot be serialized or recreated
     */
    public function __construct(
        private readonly HeadElementFactoryRegistryInterface $factoryRegistry,
        private readonly SerializationStrategyRegistryInterface $strategyRegistry,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     *
     * @return array<int|string, array<mixed>>
     */
    public function serialize(array $elements): array
    {
        $elementsData = [];

        foreach ($elements as $key => $element) {
            try {
                // Use strategy pattern to serialize the element
                $strategy = $this->strategyRegistry->getStrategyForElement($element);
                
                if ($strategy) {
                    $elementsData[$key] = $strategy->serialize($element, (string)$key);
                } else {
                    // Fallback to basic serialization if no strategy found
                    $elementsData[$key] = $this->fallbackSerialize($element);
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to serialize head element', [
                    'key' => $key,
                    'element_class' => get_class($element),
                    'exception' => $e->getMessage()
                ]);
            }
        }

        return $elementsData;
    }

    /**
     * @inheritDoc
     *
     * @param array<int|string,array<string,mixed>> $data
     * @return array<string, HeadElementInterface>
     */
    public function unserialize(array $data): array
    {
        $elements = [];

        foreach ($data as $key => $elementData) {
            try {
                $element = $this->recreateElement($elementData);
                if ($element) {
                    $elements[(string)$key] = $element;
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to unserialize head element', [
                    'key' => $key,
                    'element_data' => $elementData,
                    'exception' => $e->getMessage()
                ]);
            }
        }

        return $elements;
    }

    /**
     * Recreate element from serialized data using the factory registry
     *
     * @param array<string,mixed> $elementData the data the serialization gave
     * @return HeadElementInterface|null the element, or null when nothing can recreate it
     */
    private function recreateElement(array $elementData): ?HeadElementInterface
    {
        $type = $elementData['type'] ?? $elementData['short_type'] ?? null;
        if (!is_string($type) || $type === '') {
            return null;
        }

        // With a plugin the object manager returns the generated interceptor, which no factory is registered for.
        $type = (string) preg_replace('/\\\\Interceptor$/', '', $type);
        $attributes = $elementData['attributes'] ?? [];
        $content = $elementData['content'] ?? null;

        // Try to get factory by full class name first
        $factory = $this->factoryRegistry->getFactoryByClassName($type);

        // If not found, try to get by element type (short name)
        if (!$factory) {
            $factory = $this->factoryRegistry->getFactoryByType($type);
        }

        if (!$factory) {
            return null;
        }

        // Prepare data for factory creation
        $factoryData = ['attributes' => $attributes];

        // Add content for elements that support it (script and style)
        if ($content !== null) {
            $factoryData['content'] = $content;
        }

        // Add sort_order for proper element ordering
        $factoryData['sort_order'] = $elementData['sort_order'] ?? 100;

        return $factory->create($factoryData);
    }

    /**
     * Fallback serialization when no strategy is found
     *
     * @param HeadElementInterface $element the element to serialize
     * @return array<string, mixed> the element's data
     */
    private function fallbackSerialize(HeadElementInterface $element): array
    {
        $className = get_class($element);
        $elementType = $this->factoryRegistry->getElementTypeByClassName($className);

        return [
            'type' => $className,
            'short_type' => $elementType ?? 'unknown',
            'attributes' => $element->getAttributes(),
            'sort_order' => $element->getSortOrder(),
            'content' => null
        ];
    }
}
