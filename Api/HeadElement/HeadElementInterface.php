<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
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
     * @return string The HTML representation of the element
     */
    public function render(): string;
}
