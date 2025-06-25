<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model\Serializer\Strategy;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Model\HeadElement\ScriptElement;

/**
 * Serialization strategy for ScriptElement
 */
class ScriptElementSerializationStrategy extends AbstractSerializationStrategy
{
    /**
     * @inheritDoc
     */
    public function getElementType(): string
    {
        return 'script';
    }

    /**
     * @inheritDoc
     */
    public function getElementClassName(): string
    {
        return ScriptElement::class;
    }

    /**
     * @inheritDoc
     */
    protected function getAdditionalSerializationData(HeadElementInterface $element): array
    {
        /** @var ScriptElement $element */
        return [
            'content' => $element->getContent()
        ];
    }
}