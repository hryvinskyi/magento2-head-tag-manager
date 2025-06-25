<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model\HeadElement\Factory;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Model\HeadElement\LinkElement;
use Hryvinskyi\HeadTagManager\Model\HeadElement\LinkElementFactory as MagentoLinkElementFactory;

/**
 * Factory for creating LinkElement instances
 */
class LinkElementFactory extends AbstractHeadElementFactory
{
    public function __construct(
        private readonly MagentoLinkElementFactory $magentoFactory
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
        return 'link';
    }

    /**
     * @inheritDoc
     */
    public function getElementClassName(): string
    {
        return LinkElement::class;
    }
}