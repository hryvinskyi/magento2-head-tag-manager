<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model\HeadElement\Factory;

use Hryvinskyi\HeadTagManager\Model\HeadElement\ScriptElement;
use Hryvinskyi\HeadTagManager\Model\HeadElement\ScriptElementFactory as ScriptElementObjectFactory;

/**
 * Factory for creating ScriptElement instances
 */
class ScriptElementFactory extends AbstractHeadElementFactory
{
    /**
     * @param ScriptElementObjectFactory $elementFactory creates the element with its services injected
     */
    public function __construct(
        private readonly ScriptElementObjectFactory $elementFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    protected function createElement(array $data): ScriptElement
    {
        $element = $this->elementFactory->create();
        $content = $this->getContent($data);
        if ($content !== null) {
            $element->setContent($content);
        }

        return $element;
    }

    /**
     * @inheritDoc
     */
    protected function getDefaultSortOrder(): int
    {
        return 100;
    }

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
}
