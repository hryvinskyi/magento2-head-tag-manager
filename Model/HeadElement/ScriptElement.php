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
 * Script element
 */
class ScriptElement extends AbstractHeadElement
{
    public function __construct(
        private readonly SecureHtmlRenderer $secureHtmlRenderer,
        protected array $attributes = [],
        private string|null $content = null
    ) {
        parent::__construct($secureHtmlRenderer, $attributes);
    }

    /**
     * Set script content
     * @param string $content Script content
     * @return $this
     */
    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Get script content
     * @return string|null
     */
    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * Render the script element
     * @return string
     */
    public function render(): string
    {
        if ($this->getContent() === null) {
            return $this->getSecureHtmlRenderer()->renderTag(
                'script',
                $this->getAttributes(),
                '',
                false,
            );
        }

        return $this->getSecureHtmlRenderer()->renderTag(
            'script',
            $this->getAttributes(),
            $this->getContent(),
            false,
        );
    }
}
