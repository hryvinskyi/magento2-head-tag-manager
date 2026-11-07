<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model\HeadElement\Factory;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Model\HeadElement\LinkElement;
use Hryvinskyi\HeadTagManager\Model\HeadElement\LinkElementFactory as LinkElementObjectFactory;

/**
 * Factory for creating LinkElement instances
 */
class LinkElementFactory extends AbstractHeadElementFactory
{
    /**
     * @param LinkElementObjectFactory $elementFactory creates the element with its services injected
     */
    public function __construct(
        private readonly LinkElementObjectFactory $elementFactory
    ) {
    }

    /**
     * Create the link element
     *
     * @param array<string,mixed> $data the element's `attributes` and `sort_order`
     * @return HeadElementInterface
     */
    public function create(array $data = []): HeadElementInterface
    {
        return $this->elementFactory->create([
            'attributes' => $data['attributes'] ?? [],
            'sortOrder' => $data['sort_order'] ?? 100,
        ]);
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
