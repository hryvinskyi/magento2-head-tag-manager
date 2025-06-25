<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model\Serializer\Strategy;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Model\HeadElement\StyleElement;

/**
 * Serialization strategy for StyleElement
 */
class StyleElementSerializationStrategy extends AbstractSerializationStrategy
{
    /**
     * @inheritDoc
     */
    public function getElementType(): string
    {
        return 'style';
    }

    /**
     * @inheritDoc
     */
    public function getElementClassName(): string
    {
        return StyleElement::class;
    }

    /**
     * @inheritDoc
     */
    protected function getAdditionalSerializationData(HeadElementInterface $element): array
    {
        /** @var StyleElement $element */
        return [
            'content' => $element->getContent()
        ];
    }
}