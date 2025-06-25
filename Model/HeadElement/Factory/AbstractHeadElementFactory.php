<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model\HeadElement\Factory;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementFactoryInterface;

/**
 * Abstract base factory for head elements
 */
abstract class AbstractHeadElementFactory implements HeadElementFactoryInterface
{
    /**
     * @inheritDoc
     */
    abstract public function getElementType(): string;

    /**
     * @inheritDoc
     */
    abstract public function getElementClassName(): string;
}