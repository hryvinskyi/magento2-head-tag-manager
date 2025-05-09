<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model\HeadElement;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Magento\Framework\View\Helper\SecureHtmlRenderer;

/**
 * Abstract head element class with common attributes
 */
abstract class AbstractHeadElement implements HeadElementInterface
{
    public function __construct(
        private readonly SecureHtmlRenderer $secureHtmlRenderer,
        protected array $attributes = []
    ) {
    }

    /**
     * Set an attribute for the element
     * @param string $name Attribute name
     * @param string $value Attribute value
     * @return $this
     */
    public function setAttribute(string $name, string $value): self
    {
        $this->attributes[$name] = $value;
        return $this;
    }

    /**
     * Set multiple attributes at once
     * @param array $attributes Associative array of attributes
     * @return $this
     */
    public function setAttributes(array $attributes): self
    {
        foreach ($attributes as $name => $value) {
            $this->setAttribute($name, $value);
        }
        return $this;
    }

    /**
     * Get an attribute value
     * @param string $name Attribute name
     * @param string|null $default Default value if attribute doesn't exist
     * @return string|null
     */
    public function getAttribute(string $name, ?string $default = null): ?string
    {
        return $this->attributes[$name] ?? $default;
    }

    /**
     * Get all attributes
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Check if attribute exists
     * @param string $name Attribute name
     * @return bool
     */
    public function hasAttribute(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    /**
     * Remove an attribute
     * @param string $name Attribute name
     * @return $this
     */
    public function removeAttribute(string $name): self
    {
        unset($this->attributes[$name]);
        return $this;
    }

    /**
     * Get the SecureHtmlRenderer instance
     *
     * @return SecureHtmlRenderer
     */
    public function getSecureHtmlRenderer(): SecureHtmlRenderer
    {
        return $this->secureHtmlRenderer;
    }

    /**
     * Convert attributes to HTML string
     * @return string
     */
    protected function attributesToString(): string
    {
        $result = '';
        foreach ($this->attributes as $name => $value) {
            $result .= ' ' . htmlspecialchars($name) . '="' . htmlspecialchars($value) . '"';
        }
        return $result;
    }
}