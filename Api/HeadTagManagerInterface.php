<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Api;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementFactoryInterface;
use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;

/**
 * Collects the elements a request adds to the HTML head and renders them in sort order
 *
 * An attribute value is a string, an int, a float, a bool, a \Stringable, an array or null: `true` renders the
 * attribute in its boolean form, `false` and `null` leave it out. Only link and meta elements render an array
 * (as data attributes, a class list, a style or JSON); a script or style element with one fails to render.
 *
 * @phpstan-import-type HeadElementData from HeadElementFactoryInterface
 * @api
 */
interface HeadTagManagerInterface
{
    /**
     * Create a head element of a registered type and add it under a key
     *
     * @param string $type Element type (e.g., 'meta', 'link', 'style', 'script')
     * @param HeadElementData $data Element data (attributes, content, sort order)
     * @param string|null $key Key for replacing/removing the element later; generated from the data when null
     * @return HeadElementInterface
     * @throws \InvalidArgumentException When no factory is registered for the type, or when no key is given and the
     *     data cannot identify an element (an attribute value of an unsupported type, content that cannot be encoded)
     */
    public function createElement(
        string $type,
        array $data = [],
        ?string $key = null
    ): HeadElementInterface;

    /**
     * Add a head element, replacing any element stored under the same key
     *
     * @param HeadElementInterface $element Head element
     * @param string $key Key for replacing/removing element later
     * @return $this
     */
    public function addElement(HeadElementInterface $element, string $key): HeadTagManagerInterface;

    /**
     * Remove an element by key
     *
     * @param string $key Element key
     * @return $this
     */
    public function removeElement(string $key): HeadTagManagerInterface;

    /**
     * Check if element exists by key
     *
     * @param string $key Element key
     * @return bool
     */
    public function hasElement(string $key): bool;

    /**
     * Get element by key
     *
     * @param string $key Element key
     * @return HeadElementInterface|null
     */
    public function getElement(string $key): ?HeadElementInterface;

    /**
     * Get every element added so far, keyed by element key, in insertion order
     *
     * @return array<string, HeadElementInterface>
     */
    public function getAllElements(): array;

    /**
     * Remove every element
     *
     * @return $this
     */
    public function clear(): HeadTagManagerInterface;

    /**
     * Add a meta tag
     *
     * @param array<string,string|int|float|bool|\Stringable|array<mixed>|null> $attributes Meta attributes
     * @param string|null $key Optional key
     * @return $this
     */
    public function addMeta(array $attributes, ?string $key = null): HeadTagManagerInterface;

    /**
     * Add a meta tag with name and content
     *
     * @param string $name Meta name
     * @param string $content Meta content
     * @param string|null $key Optional key
     * @return $this
     */
    public function addMetaName(string $name, string $content, ?string $key = null): HeadTagManagerInterface;

    /**
     * Add a meta tag with property and content
     *
     * @param string $property Meta property
     * @param string $content Meta content
     * @param string|null $key Optional key
     * @return $this
     */
    public function addMetaProperty(string $property, string $content, ?string $key = null): HeadTagManagerInterface;

    /**
     * Add charset meta tag
     *
     * @param string $charset Character set
     * @return $this
     */
    public function addCharset(string $charset = 'UTF-8'): HeadTagManagerInterface;

    /**
     * Add a link element
     *
     * @param array<string,string|int|float|bool|\Stringable|array<mixed>|null> $attributes Link attributes
     * @param string|null $key Optional key
     * @return $this
     */
    public function addLink(array $attributes, ?string $key = null): HeadTagManagerInterface;

    /**
     * Add a stylesheet link
     *
     * @param string $href URL to stylesheet
     * @param array<string,string|int|float|bool|\Stringable|array<mixed>|null> $attributes Additional attributes
     * @param string|null $key Optional key
     * @return $this
     */
    public function addStylesheet(string $href, array $attributes = [], ?string $key = null): HeadTagManagerInterface;

    /**
     * Add a style element
     *
     * @param string $content CSS content
     * @param array<string,string|int|float|bool|\Stringable|array<mixed>|null> $attributes Style attributes
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
     *
     * @param array<string,string|int|float|bool|\Stringable|array<mixed>|null> $attributes Script attributes
     * @param string|null $content Script content
     * @param string|null $key Optional key
     * @return $this
     */
    public function addScript(array $attributes, ?string $content = null, ?string $key = null): HeadTagManagerInterface;

    /**
     * Add an external script
     *
     * @param string $src Script URL
     * @param array<string,string|int|float|bool|\Stringable|array<mixed>|null> $attributes Additional attributes
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
     *
     * @param string $content Script content
     * @param array<string,string|int|float|bool|\Stringable|array<mixed>|null> $attributes Additional attributes
     * @param string|null $key Optional key
     * @return $this
     */
    public function addInlineScript(
        string $content,
        array $attributes = [],
        ?string $key = null
    ): HeadTagManagerInterface;

    /**
     * Render every element, lowest sort order first, one per line
     *
     * An element that fails to render is logged and left out; the others are still rendered.
     *
     * @return string
     */
    public function render(): string;

    /**
     * Get the HTML of every element that renders, keyed by element key, in the order render() uses
     *
     * @return array<string, string>
     */
    public function getRenderedElements(): array;

    /**
     * Generate the key an element is stored under from its type and data
     *
     * Two spellings of the same tag yield the same key: attribute names are compared lower-cased and in
     * name order, `null` and `false` attributes are ignored, empty content is ignored, and the sort order
     * is not part of the identity.
     *
     * @param string $type Element type (e.g., 'meta', 'link', 'script')
     * @param HeadElementData $data Element data (attributes, content, sort order)
     * @return string Key for the element
     */
    public function generateElementKey(string $type, array $data): string;
}
