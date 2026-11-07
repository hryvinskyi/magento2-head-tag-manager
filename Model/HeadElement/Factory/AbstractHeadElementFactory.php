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
 *
 * The generated element factories pass the attributes through the object manager, which in developer mode reads an
 * `argument` key of an array as a reference to a global argument: an HTML attribute of that name empties the attributes
 * there, and survives in production mode.
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
