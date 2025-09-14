<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model\HeadElement\Factory;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Model\HeadElement\StyleElement;
use Magento\Framework\View\Helper\SecureHtmlRenderer;

/**
 * Factory for creating StyleElement instances
 */
class StyleElementFactory extends AbstractHeadElementFactory
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
        $content = $data['content'] ?? null;
        return new StyleElement($this->secureHtmlRenderer, $attributes, $content);
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