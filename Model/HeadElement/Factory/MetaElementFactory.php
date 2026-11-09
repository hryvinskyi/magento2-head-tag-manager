<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model\HeadElement\Factory;

use Hryvinskyi\HeadTagManager\Model\HeadElement\MetaElement;
use Hryvinskyi\HeadTagManager\Model\HeadElement\MetaElementFactory as MetaElementObjectFactory;

/**
 * Factory for creating MetaElement instances
 */
class MetaElementFactory extends AbstractHeadElementFactory
{
    /**
     * @param MetaElementObjectFactory $elementFactory creates the element with its services injected
     */
    public function __construct(
        private readonly MetaElementObjectFactory $elementFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    protected function createElement(array $data): MetaElement
    {
        return $this->elementFactory->create();
    }

    /**
     * @inheritDoc
     */
    protected function getDefaultSortOrder(): int
    {
        return 100;
    }

    /**
     * @inheritDoc
     */
    public function getElementType(): string
    {
        return 'meta';
    }

    /**
     * @inheritDoc
     */
    public function getElementClassName(): string
    {
        return MetaElement::class;
    }
}
