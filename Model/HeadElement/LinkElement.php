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
 * A link tag; a remote stylesheet is built by the secure renderer, every other link is written as text
 *
 * The framework's secure renderer passes a tag through its security processors, and the content security one adds
 * the host of a link's `href` to the policy's `style-src` directive. That is right for a link that loads a
 * stylesheet only: its `rel` tokens, compared without case, contain `stylesheet`, or contain `preload` while `as`
 * is `style`. Any other link (an icon, a font or script preload, a preconnect) would be filed under the wrong
 * directive, so it is written as text.
 *
 * A link carries no content, so the processor throws when it cannot read a host from the `href`, and the head tag
 * manager would then log the error and leave the whole stylesheet out of the page. So the secure renderer only sees
 * an `href` it reads a host from (see pointsAtARemoteHost()); a relative or protocol-relative `href`, an upper-case
 * scheme (`HTTPS://…`) or an empty host (`https:///a.css`) is written as text and keeps its stylesheet. A link with
 * an array attribute is written as text too, because an array has no text form there.
 *
 * The two paths can write different bytes for the same attributes. The text path closes the tag with `>` and writes
 * a `true` attribute in its bare boolean form. The secure renderer receives every value as text, so a `true`
 * attribute arrives as an empty value (`crossorigin=""`), and the HTML renderer behind it decides how the tag is
 * closed and how values are escaped: the tag renderer the container configures closes a void tag with ` />`, and the
 * framework's own HTML renderer closes it with `/>` and writes `:` and `/` as `&#x3A;` and `&#x2F;`.
 */
class LinkElement extends AbstractHeadElement
{
    /**
     * @param AttributeRendererInterface $attributeRenderer writes the attributes of a link built as text
     * @param SecureHtmlRenderer $secureHtmlRenderer builds the tag of a remote stylesheet
     * @param array<string,string|int|float|bool|\Stringable|array<mixed>|null> $attributes attribute name => value
     * @param int $sortOrder the sort order value (lower values render first)
     */
    public function __construct(
        private readonly AttributeRendererInterface $attributeRenderer,
        private readonly SecureHtmlRenderer $secureHtmlRenderer,
        array $attributes = [],
        int $sortOrder = 100
    ) {
        parent::__construct($attributes, $sortOrder);
    }

    /**
     * Render the link element
     *
     * @return string
     * @throws InvalidParamException when an attribute value has no text form
     */
    public function render(): string
    {
        $texts = $this->findTextAttributes();
        if ($texts !== null && $this->loadsAStylesheet($texts) && $this->pointsAtARemoteHost($texts['href'] ?? '')) {
            return $this->secureHtmlRenderer->renderTag('link', $texts, null, false);
        }

        return '<link' . $this->attributeRenderer->render($this->getAttributes()) . '>';
    }

    /**
     * Check whether the `rel` tokens name a stylesheet, or a preload of one
     *
     * @param array<string,string> $attributes the text attributes
     * @return bool
     */
    private function loadsAStylesheet(array $attributes): bool
    {
        $tokens = preg_split('/\s+/', strtolower($attributes['rel'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return in_array('stylesheet', $tokens, true)
            || (in_array('preload', $tokens, true) && strtolower($attributes['as'] ?? '') === 'style');
    }
}
