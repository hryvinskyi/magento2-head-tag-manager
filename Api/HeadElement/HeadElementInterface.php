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
 */
interface HeadElementInterface
{
    /**
     * Render the head element as HTML
     *
     * @return string The HTML representation of the element
     */
    public function render(): string;

    /**
     * Get the sort order for rendering
     *
     * @return int The sort order value (lower values render first)
     */
    public function getSortOrder(): int;
}
