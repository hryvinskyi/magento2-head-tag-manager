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

/**
 * A meta tag, written as text through the attribute renderer
 */
class MetaElement extends AbstractHeadElement
{
    /**
     * @param AttributeRendererInterface $attributeRenderer writes the attributes of the tag
     * @param array<string,string|int|float|bool|\Stringable|array<mixed>|null> $attributes attribute name => value
     * @param int $sortOrder the sort order value (lower values render first)
     */
    public function __construct(
        private readonly AttributeRendererInterface $attributeRenderer,
        array $attributes = [],
        int $sortOrder = 100
    ) {
        parent::__construct($attributes, $sortOrder);
    }

    /**
     * Render the meta element
     *
     * @return string
     * @throws InvalidParamException when an attribute value has no text form
     */
    public function render(): string
    {
        return '<meta' . $this->attributeRenderer->render($this->getAttributes()) . '>';
    }
}
