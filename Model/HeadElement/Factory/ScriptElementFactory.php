<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model\HeadElement\Factory;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Model\HeadElement\ScriptElement;
use Magento\Framework\View\Helper\SecureHtmlRenderer;

/**
 * Factory for creating ScriptElement instances
 */
class ScriptElementFactory extends AbstractHeadElementFactory
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
        return new ScriptElement($this->secureHtmlRenderer, $attributes, $content);
    }

    /**
     * @inheritDoc
     */
    public function getElementType(): string
    {
        return 'script';
    }

    /**
     * @inheritDoc
     */
    public function getElementClassName(): string
    {
        return ScriptElement::class;
    }
}