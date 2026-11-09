<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model\HeadElement;

use Hryvinskyi\Base\Exception\InvalidParamException;
use Magento\Framework\View\Helper\SecureHtmlRenderer;

/**
 * An inline style tag, rendered by the secure renderer so it can carry a content security nonce
 */
class StyleElement extends AbstractHeadElement
{
    /**
     * @param SecureHtmlRenderer $secureHtmlRenderer renders the tag
     * @param array<string,string|int|float|bool|\Stringable|array<mixed>|null> $attributes attribute name => value
     * @param string|null $content style content
     * @param int $sortOrder the sort order value (lower values render first)
     */
    public function __construct(
        private readonly SecureHtmlRenderer $secureHtmlRenderer,
        array $attributes = [],
        private string|null $content = null,
        int $sortOrder = 10
    ) {
        parent::__construct($attributes, $sortOrder);
    }

    /**
     * Set style content
     *
     * @param string $content style content
     * @return $this
     */
    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Get style content
     *
     * @return string|null
     */
    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * Render the style element
     *
     * @return string
     * @throws InvalidParamException when an attribute value has no text form
     */
    public function render(): string
    {
        return $this->secureHtmlRenderer->renderTag(
            'style',
            $this->getTextAttributes(),
            $this->getContent() ?? '',
            false,
        );
    }
}
