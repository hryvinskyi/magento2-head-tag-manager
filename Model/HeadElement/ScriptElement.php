<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model\HeadElement;

use Hryvinskyi\Base\Api\Html\AttributeRendererInterface;
use Hryvinskyi\Base\Exception\InvalidParamException;
use Magento\Framework\View\Helper\SecureHtmlRenderer;

/**
 * A script tag; an inline or remote script is rendered by the secure renderer, every other one is written as text
 *
 * The framework's secure renderer passes a tag through its security processors, so an inline script can carry a
 * content security nonce or hash and the host of a remote `src` is added to the policy's `script-src` directive.
 * Inline content always goes that way.
 *
 * A script without content is different: the content security processor throws when it can read no host from the
 * `src` ("Either remote URL or hashable content is required"), and the head tag manager would then log the error and
 * leave the script out of the page. So an external script reaches the secure renderer only when its `src` is one the
 * processor reads a host from (see pointsAtARemoteHost()) and every attribute has a text form; a relative or
 * protocol-relative `src`, an upper-case scheme, an empty host or an array attribute is written as text through the
 * attribute renderer, with no content, and keeps its script. A script with neither content nor `src` is text too.
 *
 * The text path writes a `true` attribute in its bare boolean form (`defer`), the secure renderer as an empty value
 * (`defer=""`), and the HTML renderer behind the secure renderer decides how values are escaped.
 */
class ScriptElement extends AbstractHeadElement
{
    /**
     * @param AttributeRendererInterface $attributeRenderer writes the attributes of a script built as text
     * @param SecureHtmlRenderer $secureHtmlRenderer renders an inline or remote script
     * @param array<string,string|int|float|bool|\Stringable|array<mixed>|null> $attributes attribute name => value
     * @param string|null $content script content
     * @param int $sortOrder the sort order value (lower values render first)
     */
    public function __construct(
        private readonly AttributeRendererInterface $attributeRenderer,
        private readonly SecureHtmlRenderer $secureHtmlRenderer,
        array $attributes = [],
        private string|null $content = null,
        int $sortOrder = 100
    ) {
        parent::__construct($attributes, $sortOrder);
    }

    /**
     * Set script content
     *
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
     *
     * @return string|null
     */
    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * Render the script element
     *
     * @return string
     * @throws InvalidParamException when an attribute value has no text form
     */
    public function render(): string
    {
        $content = $this->getContent() ?? '';
        if ($content !== '') {
            return $this->secureHtmlRenderer->renderTag('script', $this->getTextAttributes(), $content, false);
        }

        $texts = $this->findTextAttributes();
        if ($texts !== null && $this->pointsAtARemoteHost($texts['src'] ?? '')) {
            return $this->secureHtmlRenderer->renderTag('script', $texts, '', false);
        }

        return '<script' . $this->attributeRenderer->render($this->getAttributes()) . '></script>';
    }
}
