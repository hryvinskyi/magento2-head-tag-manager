<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Api;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;

interface HeadTagManagerInterface
{
    /**
     * Add a head element
     * @param HeadElementInterface $element Head element
     * @param string|null $key Optional key for replacing/removing element later
     * @return $this
     */
    public function addElement(HeadElementInterface $element, ?string $key = null): HeadTagManagerInterface;

    /**
     * Remove an element by key
     * @param string $key Element key
     * @return $this
     */
    public function removeElement(string $key): HeadTagManagerInterface;

    /**
     * Check if element exists by key
     * @param string $key Element key
     * @return bool
     */
    public function hasElement(string $key): bool;

    /**
     * Get element by key
     * @param string $key Element key
     * @return HeadElementInterface|null
     */
    public function getElement(string $key): ?HeadElementInterface;

    /**
     * Add a meta tag
     * @param array $attributes Meta attributes
     * @param string|null $key Optional key
     * @return $this
     */
    public function addMeta(array $attributes, ?string $key = null): HeadTagManagerInterface;

    /**
     * Add a meta tag with name and content
     * @param string $name Meta name
     * @param string $content Meta content
     * @param string|null $key Optional key
     * @return $this
     */
    public function addMetaName(string $name, string $content, ?string $key = null): HeadTagManagerInterface;

    /**
     * Add a meta tag with property and content
     * @param string $property Meta property
     * @param string $content Meta content
     * @param string|null $key Optional key
     * @return $this
     */
    public function addMetaProperty(string $property, string $content, ?string $key = null): HeadTagManagerInterface;

    /**
     * Add charset meta tag
     * @param string $charset Character set
     * @return $this
     */
    public function addCharset(string $charset = 'UTF-8'): HeadTagManagerInterface;

    /**
     * Add a link element
     * @param array $attributes Link attributes
     * @param string|null $key Optional key
     * @return $this
     */
    public function addLink(array $attributes, ?string $key = null): HeadTagManagerInterface;

    /**
     * Add a stylesheet link
     * @param string $href URL to stylesheet
     * @param array $attributes Additional attributes
     * @param string|null $key Optional key
     * @return $this
     */
    public function addStylesheet(string $href, array $attributes = [], ?string $key = null): HeadTagManagerInterface;

    /**
     * Add a style element
     * @param string $content CSS content
     * @param array $attributes Style attributes
     * @param string|null $key Optional key
     * @return $this
     */
    public function addInlineStyle(
        string $content,
        array $attributes = [],
        ?string $key = null
    ): HeadTagManagerInterface;

    /**
     * Add a script element
     * @param array $attributes Script attributes
     * @param string|null $content Script content
     * @param string|null $key Optional key
     * @return $this
     */
    public function addScript(array $attributes, ?string $content = null, ?string $key = null): HeadTagManagerInterface;

    /**
     * Add an external script
     * @param string $src Script URL
     * @param array $attributes Additional attributes
     * @param string|null $key Optional key
     * @return $this
     */
    public function addExternalScript(
        string $src,
        array $attributes = [],
        ?string $key = null
    ): HeadTagManagerInterface;

    /**
     * Add an inline script
     * @param string $content Script content
     * @param array $attributes Additional attributes
     * @param string|null $key Optional key
     * @return $this
     */
    public function addInlineScript(
        string $content,
        array $attributes = [],
        ?string $key = null
    ): HeadTagManagerInterface;

    /**
     * Render all head elements
     * @return string
     */
    public function render(): string;

    /**
     * Get array of rendered head elements
     * @return array
     */
    public function getRenderedElements(): array;
}