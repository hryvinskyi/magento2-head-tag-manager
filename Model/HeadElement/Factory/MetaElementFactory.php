<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model\HeadElement\Factory;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Model\HeadElement\MetaElement;
use Hryvinskyi\HeadTagManager\Model\HeadElement\MetaElementFactory as MagentoMetaElementFactory;

/**
 * Factory for creating MetaElement instances
 */
class MetaElementFactory extends AbstractHeadElementFactory
{
    public function __construct(
        private readonly MagentoMetaElementFactory $magentoFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function create(array $data = []): HeadElementInterface
    {
        return $this->magentoFactory->create($data);
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