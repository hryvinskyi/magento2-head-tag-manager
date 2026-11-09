<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\Serializer\Stub;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;

/**
 * A head element the strategy tests serialize
 */
class TestElement implements HeadElementInterface
{
    /** @var array<string,string> */
    private array $attributes = [];

    /** @var int */
    private int $sortOrder = 100;

    /**
     * Set one attribute
     *
     * @param string $name the attribute name
     * @param string $value the attribute value
     * @return $this
     */
    public function setAttribute(string $name, string $value): self
    {
        $this->attributes[$name] = $value;
        return $this;
    }

    /**
     * Replace all attributes
     *
     * @param array<string,string> $attributes attribute name => value
     * @return $this
     */
    public function setAttributes(array $attributes): self
    {
        $this->attributes = $attributes;
        return $this;
    }

    /**
     * Get one attribute
     *
     * @param string $name the attribute name
     * @param string|null $default the value when the attribute is absent
     * @return string|null
     */
    public function getAttribute(string $name, ?string $default = null): ?string
    {
        return $this->attributes[$name] ?? $default;
    }

    /**
     * @inheritDoc
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Tell whether an attribute is set
     *
     * @param string $name the attribute name
     * @return bool
     */
    public function hasAttribute(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    /**
     * Remove one attribute
     *
     * @param string $name the attribute name
     * @return $this
     */
    public function removeAttribute(string $name): self
    {
        unset($this->attributes[$name]);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function render(): string
    {
        return '<test>';
    }

    /**
     * @inheritDoc
     */
    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    /**
     * Set the sort order
     *
     * @param int $sortOrder the sort order value
     * @return $this
     */
    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }
}
