<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model;

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

    public function __construct(
        private readonly HeadElementFactoryRegistryInterface $factoryRegistry
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
        $this->elements[$key] = $element;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function removeElement(string $key): self
    {
        if (isset($this->elements[$key])) {
            unset($this->elements[$key]);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function hasElement(string $key): bool
    {
        return isset($this->elements[$key]);
    }

    /**
     * @inheritDoc
     */
    public function getElement(string $key): ?HeadElementInterface
    {
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
     * Clear all elements
     */
    public function clear(): self
    {
        $this->elements = [];
        return $this;
    }

    /**
     * Get all elements
     *
     * @return HeadElementInterface[]
     */
    public function getAllElements(): array
    {
        return $this->elements;
    }

}