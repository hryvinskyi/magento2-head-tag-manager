<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model\Serializer\Strategy;

use Hryvinskyi\HeadTagManager\Model\HeadElement\MetaElement;

/**
 * Serialization strategy for MetaElement
 */
class MetaElementSerializationStrategy extends AbstractSerializationStrategy
{
    /**
     * @inheritDoc
     */
    public function getElementType(): string
    {
        return 'meta';
    }

    /**
     * @inheritDoc
     */
    public function getElementClassName(): string
    {
        return MetaElement::class;
    }
}