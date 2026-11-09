<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\Cache\Fixture;

use Hryvinskyi\HeadTagManager\Model\HeadElement\AbstractHeadElement;
use Hryvinskyi\HeadTagManager\Model\HeadElement\Factory\AbstractHeadElementFactory;

/**
 * The factory a third party registers for its custom element type
 */
class CustomHeadElementFactory extends AbstractHeadElementFactory
{
    /**
     * @inheritDoc
     */
    protected function createElement(array $data): AbstractHeadElement
    {
        return new CustomHeadElement();
    }

    /**
     * @inheritDoc
     */
    protected function getDefaultSortOrder(): int
    {
        return 50;
    }

    /**
     * @inheritDoc
     */
    public function getElementType(): string
    {
        return 'custom';
    }

    /**
     * @inheritDoc
     */
    public function getElementClassName(): string
    {
        return CustomHeadElement::class;
    }
}
