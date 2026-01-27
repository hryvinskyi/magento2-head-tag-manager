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
use Magento\Framework\View\Helper\SecureHtmlRenderer;

/**
 * Factory for creating LinkElement instances
 */
class LinkElementFactory extends AbstractHeadElementFactory
{
    public function __construct(
        private readonly SecureHtmlRenderer $secureHtmlRenderer
    ) {
    }

    /**
     * @inheritDoc
     */
    public function create(array $data = []): HeadElementInterface
    {
        $attributes = $data['attributes'] ?? [];
        $sortOrder = $data['sort_order'] ?? 100;
        return new LinkElement($this->secureHtmlRenderer, $attributes, $sortOrder);
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