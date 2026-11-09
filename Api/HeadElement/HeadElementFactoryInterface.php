<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Api\HeadElement;

/**
 * Creates the head elements of one type
 *
 * @phpstan-type HeadElementData array{
 *     attributes?: array<string, string|int|float|bool|\Stringable|array<mixed>|null>,
 *     content?: string|null,
 *     sort_order?: int
 * }
 * @api
 */
interface HeadElementFactoryInterface
{
    /**
     * Create a head element from its data
     *
     * Missing data takes the element type's defaults; `content` applies only to the types that carry content.
     *
     * @param HeadElementData $data the element's attributes, content and sort order
     * @return HeadElementInterface
     */
    public function create(array $data = []): HeadElementInterface;

    /**
     * Get element type identifier
     *
     * @return string
     */
    public function getElementType(): string;

    /**
     * Get element class name
     *
     * @return class-string<HeadElementInterface>
     */
    public function getElementClassName(): string;
}
