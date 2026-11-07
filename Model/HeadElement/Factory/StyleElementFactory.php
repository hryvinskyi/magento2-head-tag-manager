<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model\HeadElement\Factory;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Model\HeadElement\StyleElement;
use Hryvinskyi\HeadTagManager\Model\HeadElement\StyleElementFactory as StyleElementObjectFactory;

/**
 * Factory for creating StyleElement instances
 */
class StyleElementFactory extends AbstractHeadElementFactory
{
    /**
     * @param StyleElementObjectFactory $elementFactory creates the element with its services injected
     */
    public function __construct(
        private readonly StyleElementObjectFactory $elementFactory
    ) {
    }

    /**
     * Create the style element
     *
     * @param array<string,mixed> $data the element's `attributes`, `content` and `sort_order`
     * @return HeadElementInterface
     */
    public function create(array $data = []): HeadElementInterface
    {
        return $this->elementFactory->create([
            'attributes' => $data['attributes'] ?? [],
            'content' => $data['content'] ?? null,
            'sortOrder' => $data['sort_order'] ?? 10,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function getElementType(): string
    {
        return 'style';
    }

    /**
     * @inheritDoc
     */
    public function getElementClassName(): string
    {
        return StyleElement::class;
    }
}
