<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\HeadElement\Fixture;

use Hryvinskyi\Base\Api\Html\AttributeRendererInterface;
use Hryvinskyi\Base\Model\Html\AttributeRenderer;
use Hryvinskyi\Base\Model\Html\CssOptions;
use Hryvinskyi\Base\Model\Html\HtmlEncoder;
use Hryvinskyi\Base\Model\Html\TextConverter;
use Hryvinskyi\Base\Model\Json\JsonEncoder;
use Magento\Framework\Escaper;
use Magento\Framework\ZendEscaper;

/**
 * Builds the real attribute renderer by hand, so rendered elements can be compared byte for byte
 */
class RealAttributeRenderer
{
    /**
     * The attribute order Base's configuration gives the renderer.
     */
    private const ATTRIBUTE_ORDER = [
        'type',
        'id',
        'class',
        'name',
        'value',

        'href',
        'src',
        'srcset',
        'form',
        'action',
        'method',

        'selected',
        'checked',
        'readonly',
        'disabled',
        'multiple',

        'size',
        'maxlength',
        'width',
        'height',
        'rows',
        'cols',

        'alt',
        'title',
        'rel',
        'media',
    ];

    /**
     * The attributes whose array value Base's configuration renders as prefixed attributes.
     */
    private const DATA_ATTRIBUTES = ['aria', 'data', 'data-ng', 'ng'];

    /**
     * Builds the renderer with the configured attribute order.
     *
     * @return AttributeRendererInterface
     */
    public function build(): AttributeRendererInterface
    {
        return new AttributeRenderer(
            new HtmlEncoder(new ZendEscaper(), new Escaper()),
            new JsonEncoder(),
            new CssOptions(new TextConverter(), true),
            new TextConverter(),
            self::ATTRIBUTE_ORDER,
            self::DATA_ATTRIBUTES,
            true
        );
    }
}
