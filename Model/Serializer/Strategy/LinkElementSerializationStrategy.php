<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model\Serializer\Strategy;

use Hryvinskyi\HeadTagManager\Model\HeadElement\LinkElement;

/**
 * Serialization strategy for LinkElement
 */
class LinkElementSerializationStrategy extends AbstractSerializationStrategy
{
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