<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model;

use Hryvinskyi\HeadTagManager\Api\Cache\HeadElementCacheStrategyInterface;
use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Api\HeadTagManagerInterface;
use Hryvinskyi\HeadTagManager\Api\Registry\HeadElementFactoryRegistryInterface;

class HeadTagManager implements HeadTagManagerInterface
{
    /**
     * Collection of head elements
     * @var HeadElementInterface[]
     */
    protected array $elements = [];

    /**
     * Flag to track if elements have been loaded from cache
     */
    private bool $elementsLoaded = false;

    /**
     * Flag to track if elements have been modified since last cache save
     */
    private bool $elementsModified = false;

    public function __construct(
        private readonly HeadElementFactoryRegistryInterface $factoryRegistry,
        private readonly HeadElementCacheStrategyInterface $cacheStrategy
    ) {
    }

    /**
     * @inheritDoc
     */
    public function createElement(
        string $type,
        array $data = [],
        ?string $key = null
    ): HeadElementInterface {
        $this->ensureElementsLoaded();

        $factory = $this->factoryRegistry->getFactoryByType($type);
        if (!$factory) {
            throw new \InvalidArgumentException(sprintf('No factory found for element type: %s', $type));
        }

        $element = $factory->create($data);

        // Generate key if not provided
        if ($key === null) {
            $key = $this->generateElementKey($type, $data);
        }

        $this->addElement($element, $key);

        return $element;
    }

    /**
     * @inheritDoc
     */
    public function addElement(HeadElementInterface $element, string $key): self
    {
        $this->ensureElementsLoaded();
        $this->elements[$key] = $element;
        $this->elementsModified = true;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function removeElement(string $key): self
    {
        $this->ensureElementsLoaded();

        if (isset($this->elements[$key])) {
            unset($this->elements[$key]);
            $this->elementsModified = true;
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function hasElement(string $key): bool
    {
        $this->ensureElementsLoaded();
        return isset($this->elements[$key]);
    }

    /**
     * @inheritDoc
     */
    public function getElement(string $key): ?HeadElementInterface
    {
        $this->ensureElementsLoaded();
        return $this->elements[$key] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function addMeta(array $attributes, ?string $key = null): self
    {
        $this->createElement('meta', ['attributes' => $attributes], $key);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addMetaName(string $name, string $content, ?string $key = null): self
    {
        $data = ['attributes' => ['name' => $name, 'content' => $content]];
        $metaKey = $key ?? $this->generateElementKey('meta', $data);
        $this->createElement('meta', $data, $metaKey);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addMetaProperty(string $property, string $content, ?string $key = null): self
    {
        $data = ['attributes' => ['property' => $property, 'content' => $content]];
        $metaKey = $key ?? $this->generateElementKey('meta', $data);
        $this->createElement('meta', $data, $metaKey);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addCharset(string $charset = 'UTF-8'): self
    {
        $data = ['attributes' => ['charset' => $charset]];
        $this->createElement('meta', $data, 'charset');

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addLink(array $attributes, ?string $key = null): self
    {
        $this->createElement('link', ['attributes' => $attributes], $key);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addStylesheet(string $href, array $attributes = [], ?string $key = null): self
    {
        $data = ['attributes' => ['rel' => 'stylesheet', 'href' => $href] + $attributes];
        $cssKey = $key ?? $this->generateElementKey('link', $data);
        $this->createElement('link', $data, $cssKey);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addScript(array $attributes, ?string $content = null, ?string $key = null): self
    {
        $data = ['attributes' => $attributes, 'content' => $content];
        $key = $key ?? $this->generateElementKey('script', $data);
        $this->createElement('script', $data, $key);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addExternalScript(string $src, array $attributes = [], ?string $key = null): self
    {
        $data = ['attributes' => ['src' => $src] + $attributes];
        $scriptKey = $key ?? $this->generateElementKey('script', $data);
        $this->createElement('script', $data, $scriptKey);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addInlineScript(string $content, array $attributes = [], ?string $key = null): self
    {
        $data = ['attributes' => $attributes, 'content' => $content];
        $scriptKey = $key ?? $this->generateElementKey('script', $data);
        $this->createElement('script', $data, $scriptKey);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addInlineStyle(string $content, array $attributes = [], ?string $key = null): self
    {
        $data = ['attributes' => $attributes, 'content' => $content];
        $styleKey = $key ?? $this->generateElementKey('style', $data);
        $this->createElement('style', $data, $styleKey);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function render(): string
    {
        $this->ensureElementsLoaded();
        $this->saveToCacheIfModified();

        $output = '';
        foreach ($this->elements as $element) {
            $output .= $element->render() . PHP_EOL;
        }

        return $output;
    }

    /**
     * @inheritDoc
     */
    public function getRenderedElements(): array
    {
        $this->ensureElementsLoaded();
        $this->saveToCacheIfModified();

        return array_map(static function ($element) {
            return $element->render();
        }, $this->elements);
    }

    /**
     * @inheritDoc
     */
    public function generateElementKey(string $type, array $data): string
    {
        // Create a unique key based on type and data content
        $keyData = [
            'type' => $type,
            'data' => $data
        ];

        return $type . '_' . md5(serialize($keyData));
    }

    /**
     * Clear all elements and cache
     */
    public function clear(): self
    {
        $this->elements = [];
        $this->elementsLoaded = true;
        $this->elementsModified = true;
        $this->cacheStrategy->clear();

        return $this;
    }

    /**
     * Get all elements
     *
     * @return HeadElementInterface[]
     */
    public function getAllElements(): array
    {
        $this->ensureElementsLoaded();
        return $this->elements;
    }

    /**
     * Ensure elements are loaded from cache if needed
     */
    private function ensureElementsLoaded(): void
    {
        if (!$this->elementsLoaded) {
            $cachedElements = $this->cacheStrategy->load();
            if (!empty($cachedElements)) {
                $this->elements = $cachedElements;
            }
            $this->elementsLoaded = true;
        }
    }

    /**
     * Force save to cache
     */
    public function saveToCache(): self
    {
        $this->ensureElementsLoaded();
        $this->cacheStrategy->save($this->elements);
        $this->elementsModified = false;

        return $this;
    }

    /**
     * Save to cache only if elements have been modified
     */
    private function saveToCacheIfModified(): void
    {
        if ($this->elementsModified) {
            $this->saveToCache();
        }
    }
}