<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Api\HeadElement;

/**
 * Interface for head tag elements
 *
 * @api
 */
interface HeadElementInterface
{
    /**
     * Render the head element as HTML
     *
     * @return string The HTML representation of the element
     * @throws \InvalidArgumentException When the element cannot be rendered, e.g. an attribute value has no text form
     */
    public function render(): string;

    /**
     * Get all attributes
     *
     * `true` renders the attribute in its boolean form, `false` and `null` leave it out; whether an array renders
     * depends on the element.
     *
     * @return array<string,string|int|float|bool|\Stringable|array<mixed>|null> attribute name => value
     */
    public function getAttributes(): array;

    /**
     * Get the sort order for rendering
     *
     * @return int The sort order value (lower values render first)
     */
    public function getSortOrder(): int;
}
