<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model\HeadElement;

use Hryvinskyi\Base\Exception\InvalidParamException;
use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;

/**
 * Abstract head element class with common attributes
 *
 * The base class holds the attributes and the sort order and takes no renderer: each element takes the one it
 * writes its tag with. A meta element writes its tag as text through the attribute renderer. A style element builds
 * its tag through the framework's secure renderer, so the tag can carry a content security nonce or hash. Script and
 * link elements take both: an inline script, a remote external script and a remote stylesheet go through the secure
 * renderer, so the tag carries its nonce or its host reaches the content security policy, and every other script or
 * link is written as text through the attribute renderer.
 *
 * An attribute value is a string, an int, a float, a bool, a \Stringable, an array or null. Whichever renderer an
 * element uses, `true` renders the attribute in its boolean form and `false` and `null` leave it out. An array is
 * written only by the attribute renderer (data attributes, a class list, a style, JSON), so it renders on a meta
 * element and on a script or link written as text; a style element and an inline script refuse it, and an external
 * script or a remote stylesheet that carries one is written as text.
 *
 * A script or link without content goes through the secure renderer only when the content security processor
 * behind it can read a host from its `src` or `href`; see pointsAtARemoteHost().
 */
abstract class AbstractHeadElement implements HeadElementInterface
{
    /**
     * A URL the content security processor reads a host from
     *
     * A lower-case `http://` or `https://`, optional user information, a host that does not start with `:` or `@`,
     * an optional port of one to five digits, then a path, a query, a fragment or the end. Whitespace is never part
     * of the host. The port is captured, because the processor's URL parse also refuses one above 65535.
     */
    private const REMOTE_URL_PATTERN = '~^https?://(?:[^/?#@\s]*@)?[^/?#:@\s][^/?#:@\s]*'
        . '(?::(\d{1,5}))?(?:[/?#]|\z)~';

    /**
     * The highest port the processor's URL parse accepts
     */
    private const HIGHEST_PORT = 65535;

    /**
     * Attribute name => value, names in lower case
     *
     * @var array<string,string|int|float|bool|\Stringable|array<mixed>|null>
     */
    protected array $attributes = [];

    /**
     * Names are stored as setAttribute() stores them, so `HREF` passed here is found as `href`.
     *
     * @param array<string,string|int|float|bool|\Stringable|array<mixed>|null> $attributes attribute name => value
     * @param int $sortOrder the sort order value (lower values render first)
     */
    public function __construct(
        array $attributes = [],
        protected int $sortOrder = 100
    ) {
        $this->setAttributes($attributes);
    }

    /**
     * Get the sort order for rendering
     *
     * @return int The sort order value (lower values render first)
     */
    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    /**
     * Set the sort order for rendering
     *
     * @param int $sortOrder The sort order value
     * @return $this
     */
    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }

    /**
     * Set an attribute for the element
     *
     * Attribute names are case-insensitive in HTML and are stored in lower case, so `SRC` and `src` are one
     * attribute, and the content security processor, which reads `src` and `href`, finds them.
     *
     * @param string $name Attribute name
     * @param string|int|float|bool|\Stringable|array<mixed>|null $value Attribute value
     * @return $this
     */
    public function setAttribute(string $name, string|int|float|bool|\Stringable|array|null $value): self
    {
        $this->attributes[strtolower($name)] = $value;
        return $this;
    }

    /**
     * Set multiple attributes at once
     *
     * @param array<string,string|int|float|bool|\Stringable|array<mixed>|null> $attributes name => value
     * @return $this
     */
    public function setAttributes(array $attributes): self
    {
        foreach ($attributes as $name => $value) {
            // A name that is a number is held by an array as an int key, whatever the caller wrote.
            $this->setAttribute((string)$name, $value);
        }
        return $this;
    }

    /**
     * Get an attribute value
     *
     * @param string $name Attribute name
     * @param string|int|float|bool|\Stringable|array<mixed>|null $default Default value if attribute doesn't exist
     * @return string|int|float|bool|\Stringable|array<mixed>|null
     */
    public function getAttribute(
        string $name,
        string|int|float|bool|\Stringable|array|null $default = null
    ): string|int|float|bool|\Stringable|array|null {
        return $this->attributes[strtolower($name)] ?? $default;
    }

    /**
     * @inheritDoc
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Check if attribute exists
     *
     * @param string $name Attribute name
     * @return bool
     */
    public function hasAttribute(string $name): bool
    {
        return isset($this->attributes[strtolower($name)]);
    }

    /**
     * Remove an attribute
     *
     * @param string $name Attribute name
     * @return $this
     */
    public function removeAttribute(string $name): self
    {
        unset($this->attributes[strtolower($name)]);
        return $this;
    }

    /**
     * Get the attributes as the text values a tag renderer writes as `name="value"`
     *
     * `false` and `null` leave the attribute out, `true` becomes an empty value (the boolean form `name=""`), an
     * int, a float or a \Stringable becomes its text, and an array is refused. A tag renderer that casts every value
     * to a string would otherwise render `false` as an empty, and so present, attribute and an array as "Array".
     *
     * @return array<string, string> attribute name => text, in the order the attributes were set
     * @throws InvalidParamException when an attribute value has no text form
     */
    protected function getTextAttributes(): array
    {
        $texts = [];
        foreach ($this->attributes as $name => $value) {
            // PHP keeps a name made of digits as an int key.
            $text = $this->attributeText((string)$name, $value);
            if ($text !== null) {
                $texts[$name] = $text;
            }
        }

        return $texts;
    }

    /**
     * Get the text attributes, or null when an attribute value has no text form
     *
     * The same values as getTextAttributes(), for an element that writes itself as text when the secure renderer
     * cannot take its attributes.
     *
     * @return array<string, string>|null attribute name => text, in the order the attributes were set
     */
    protected function findTextAttributes(): ?array
    {
        $texts = [];
        foreach ($this->attributes as $name => $value) {
            if ($value !== null && !is_scalar($value) && !$value instanceof \Stringable) {
                return null;
            }
            // PHP keeps a name made of digits as an int key.
            $text = $this->attributeText((string)$name, $value);
            if ($text !== null) {
                $texts[$name] = $text;
            }
        }

        return $texts;
    }

    /**
     * Check whether the content security processor behind the secure renderer reads a host from a URL
     *
     * The processor reads the host by comparing the URL's scheme with a lower-case `http` or `https`, with case, and
     * throws when a tag it is given carries neither such a host nor content ("Either remote URL or hashable content
     * is required"). The head tag manager would then log the error and leave the element out of the page. A URL
     * this check refuses (relative, protocol-relative, an upper-case scheme, an empty host, a port the processor's
     * parse refuses) is therefore never handed to the secure renderer without content.
     *
     * @param string $url the URL of the tag's `src` or `href`
     * @return bool
     */
    protected function pointsAtARemoteHost(string $url): bool
    {
        if (preg_match(self::REMOTE_URL_PATTERN, $url, $matches) !== 1) {
            return false;
        }
        $port = $matches[1] ?? '';

        return $port === '' || (int)$port <= self::HIGHEST_PORT;
    }

    /**
     * Get the text one attribute value renders as, or null when the attribute is left out
     *
     * The setters declare the value types; an array has no text form for a tag built by the secure renderer.
     *
     * @param string $name the attribute name, for the error message
     * @param mixed $value the attribute value
     * @return string|null
     * @throws InvalidParamException when the value has no text form
     */
    private function attributeText(string $name, mixed $value): ?string
    {
        if ($value === null || $value === false) {
            return null;
        }
        if ($value === true) {
            return '';
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value) || $value instanceof \Stringable) {
            return (string)$value;
        }

        throw new InvalidParamException(
            sprintf('The value of the "%s" attribute, of type %s, has no text form.', $name, get_debug_type($value))
        );
    }
}
