<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model\HeadElement\Factory;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
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
     * Create the meta element
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
