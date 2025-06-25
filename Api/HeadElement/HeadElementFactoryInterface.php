<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Api\HeadElement;

interface HeadElementFactoryInterface
{
    /**
     * Create head element
     *
     * @param array $data
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
     * @return string
     */
    public function getElementClassName(): string;
}