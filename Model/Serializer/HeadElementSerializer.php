<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model\Serializer;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementFactoryInterface;
use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Api\Registry\HeadElementFactoryRegistryInterface;
use Hryvinskyi\HeadTagManager\Api\Serializer\HeadElementSerializerInterface;
use Hryvinskyi\HeadTagManager\Api\Serializer\StorableAttributesInterface;
use Hryvinskyi\HeadTagManager\Api\Serializer\Strategy\SerializationStrategyRegistryInterface;
use Psr\Log\LoggerInterface;

/**
 * Default serializer for head elements using factory registry and strategy pattern
 *
 * A serialized entry names the element's class (`type`) and its registered element type (`short_type`). An entry
 * is recreated through the factory registered for the class, or, when none is, through the factory registered for
 * the element type, so a subclass the registry has no factory for still comes back as its base element. The class
 * is left out of an entry whose element type already finds the same factory, which every element of a registered
 * class is; the stored data then names only what a factory lookup needs.
 */
class HeadElementSerializer implements HeadElementSerializerInterface
{
    private const INTERCEPTOR_SUFFIX = '\\Interceptor';

    /**
     * @param HeadElementFactoryRegistryInterface $factoryRegistry finds the factory that recreates an element
     * @param SerializationStrategyRegistryInterface $strategyRegistry finds the strategy that serializes an element
     * @param LoggerInterface $logger records an element that cannot be serialized or recreated
     * @param StorableAttributesInterface $storableAttributes puts attribute values in a form that survives encoding
     */
    public function __construct(
        private readonly HeadElementFactoryRegistryInterface $factoryRegistry,
        private readonly SerializationStrategyRegistryInterface $strategyRegistry,
        private readonly LoggerInterface $logger,
        private readonly StorableAttributesInterface $storableAttributes
    ) {
    }

    /**
     * @inheritDoc
     */
    public function serialize(array $elements): array
    {
        $elementsData = [];

        foreach ($elements as $key => $element) {
            try {
                $strategy = $this->strategyRegistry->getStrategyForElement($element);

                $data = $strategy !== null
                    ? $strategy->serialize($element, (string)$key)
                    : $this->fallbackSerialize($element);
                $elementsData[$key] = $this->withoutRedundantType($data);
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
     */
    public function unserialize(array $data): array
    {
        $elements = [];

        foreach ($data as $key => $elementData) {
            try {
                $element = is_array($elementData) ? $this->recreateElement($elementData) : null;
                if ($element === null) {
                    $this->logger->warning('Head element cannot be recreated: no factory is registered for it', [
                        'key' => $key,
                        'type' => is_array($elementData) ? ($elementData['type'] ?? null) : null,
                        'short_type' => is_array($elementData) ? ($elementData['short_type'] ?? null) : null,
                    ]);
                    continue;
                }
                $elements[(string)$key] = $element;
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
     * @param array<mixed> $elementData the data the serialization gave
     * @return HeadElementInterface|null the element, or null when nothing can recreate it
     */
    private function recreateElement(array $elementData): ?HeadElementInterface
    {
        $factory = $this->findFactory($elementData['type'] ?? null, $elementData['short_type'] ?? null);
        if ($factory === null) {
            return null;
        }

        $factoryData = ['attributes' => $this->attributesFrom($elementData['attributes'] ?? null)];

        // Content is only present for the elements that carry it (script and style)
        $content = $elementData['content'] ?? null;
        if (is_string($content)) {
            $factoryData['content'] = $content;
        }

        // An entry without a sort order leaves the factory's own default in place
        $sortOrder = $elementData['sort_order'] ?? null;
        if (is_int($sortOrder) || (is_string($sortOrder) && is_numeric($sortOrder))) {
            $factoryData['sort_order'] = (int)$sortOrder;
        }

        return $factory->create($factoryData);
    }

    /**
     * Keep the attributes of a decoded entry that have a value type an element accepts
     *
     * Arrays are kept: link and meta render them as data attributes, class lists or JSON.
     *
     * @param mixed $attributes the decoded `attributes` value
     * @return array<string, string|int|float|bool|array<mixed>|null> attribute name => value
     */
    private function attributesFrom(mixed $attributes): array
    {
        if (!is_array($attributes)) {
            return [];
        }

        $typed = [];
        foreach ($attributes as $name => $value) {
            if ($value === null || is_scalar($value) || is_array($value)) {
                $typed[(string)$name] = $value;
            }
        }

        return $typed;
    }

    /**
     * The serialized data without its class when its element type alone finds the same factory
     *
     * @param array<string,mixed> $elementData
     * @return array<string,mixed>
     */
    private function withoutRedundantType(array $elementData): array
    {
        $type = $elementData['type'] ?? null;
        $shortType = $elementData['short_type'] ?? null;
        if (!is_string($type) || !is_string($shortType) || $shortType === '') {
            return $elementData;
        }

        $factory = $this->factoryRegistry->getFactoryByType($shortType);
        if ($factory !== null && $factory === $this->findFactory($type, null)) {
            unset($elementData['type']);
        }

        return $elementData;
    }

    /**
     * Find the factory for a serialized entry: by class first, then by element type
     *
     * @param mixed $type the serialized class name
     * @param mixed $shortType the serialized element type
     * @return HeadElementFactoryInterface|null
     */
    private function findFactory(mixed $type, mixed $shortType): ?HeadElementFactoryInterface
    {
        $factory = null;
        if (is_string($type) && $type !== '') {
            $factory = $this->factoryRegistry->getFactoryByClassName($this->withoutInterceptorSuffix($type));
        }

        if ($factory === null && is_string($shortType) && $shortType !== '') {
            $factory = $this->factoryRegistry->getFactoryByType($shortType);
        }

        return $factory;
    }

    /**
     * Fallback serialization when no strategy is found
     *
     * A \Stringable attribute value, at any depth, is stored as its text, as the strategies store it.
     *
     * @param HeadElementInterface $element the element to serialize
     * @return array<string, mixed> the element's data
     */
    private function fallbackSerialize(HeadElementInterface $element): array
    {
        $className = $this->withoutInterceptorSuffix(get_class($element));
        return [
            'type' => $className,
            'short_type' => $this->elementTypeOf($className) ?? 'unknown',
            'attributes' => $this->storableAttributes->fromAttributes($element->getAttributes()),
            'sort_order' => $element->getSortOrder(),
            'content' => null
        ];
    }

    /**
     * Resolve the registered element type of a class: its own, or the nearest ancestor's
     *
     * @param string $className the element's class
     * @return string|null the element type, or null when neither the class nor an ancestor is registered
     */
    private function elementTypeOf(string $className): ?string
    {
        $ancestors = class_exists($className) ? class_parents($className) : [];
        foreach ([$className, ...array_values($ancestors === false ? [] : $ancestors)] as $candidate) {
            $elementType = $this->factoryRegistry->getElementTypeByClassName($candidate);
            if ($elementType !== null) {
                return $elementType;
            }
        }

        return null;
    }

    /**
     * Name the class an object manager interceptor wraps; any other class name is returned unchanged
     *
     * @param string $className a class name, possibly a generated interceptor's
     * @return string
     */
    private function withoutInterceptorSuffix(string $className): string
    {
        if (str_ends_with($className, self::INTERCEPTOR_SUFFIX)) {
            return substr($className, 0, -strlen(self::INTERCEPTOR_SUFFIX));
        }

        return $className;
    }
}
