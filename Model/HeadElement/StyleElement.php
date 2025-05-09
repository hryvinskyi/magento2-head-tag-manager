<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model\HeadElement;

use Magento\Framework\View\Helper\SecureHtmlRenderer;

/**
 * Style element
 */
class StyleElement extends AbstractHeadElement
{
    public function __construct(
        private readonly SecureHtmlRenderer $secureHtmlRenderer,
        protected array $attributes = [],
        private string|null $content = null
    ) {
        parent::__construct($secureHtmlRenderer, $attributes);
    }

    /**
     * Set style content
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
     * @return string|null
     */
    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * Render the style element
     * @return string
     */
    public function render(): string
    {
        if ($this->getContent() === null) {
            return $this->getSecureHtmlRenderer()->renderTag(
                'style',
                $this->getAttributes(),
                '',
                false,
            );
        }

        return $this->getSecureHtmlRenderer()->renderTag(
            'style',
            $this->getAttributes(),
            $this->getContent(),
            false,
        );
    }
}