<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model\HeadElement\Factory;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementFactoryInterface;
use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Model\HeadElement\AbstractHeadElement;

/**
 * Abstract base factory for head elements
 *
 * The element is created with no data and the caller's data is applied through its setters, so attribute values
 * never pass through the object manager's argument handling, which reads some array keys as instructions.
 *
 * The setters are strictly typed, while the object manager used to coerce constructor arguments, and layout
 * arguments arrive as strings. So the data is read the way that coercion read it: a numeric `sort_order` becomes an
 * int and any other one takes the type's default, scalar or \Stringable content becomes its text, and an attribute
 * name that is a number (an array holds a numeric string key as an int) is applied as text.
 *
 * @phpstan-import-type HeadElementData from HeadElementFactoryInterface
 */
abstract class AbstractHeadElementFactory implements HeadElementFactoryInterface
{
    /**
     * @inheritDoc
     */
    public function create(array $data = []): HeadElementInterface
    {
        $element = $this->createElement($data);
        $element->setAttributes($data['attributes'] ?? []);
        $element->setSortOrder($this->sortOrder($data['sort_order'] ?? null));

        return $element;
    }

    /**
     * Get the text of the content in the element data, or null when it carries none
     *
     * @param HeadElementData $data the element data
     * @return string|null
     * @throws \InvalidArgumentException when the content has no text form
     */
    protected function getContent(array $data): ?string
    {
        return $this->contentText($data['content'] ?? null);
    }

    /**
     * Create the element with no data, applying what only this element type carries
     *
     * @param HeadElementData $data the element data
     * @return AbstractHeadElement
     */
    abstract protected function createElement(array $data): AbstractHeadElement;

    /**
     * Get the sort order an element of this type takes when its data has none
     *
     * @return int
     */
    abstract protected function getDefaultSortOrder(): int;

    /**
     * @inheritDoc
     */
    abstract public function getElementType(): string;

    /**
     * Get the sort order a value stands for: a numeric one as an int, anything else the type's default
     *
     * @param mixed $sortOrder the value of the data's `sort_order`, as the caller passed it
     * @return int
     */
    private function sortOrder(mixed $sortOrder): int
    {
        return is_numeric($sortOrder) ? (int)$sortOrder : $this->getDefaultSortOrder();
    }

    /**
     * Get the text of a content value: a scalar or a \Stringable becomes its text, null stays null
     *
     * @param mixed $content the value of the data's `content`, as the caller passed it
     * @return string|null
     * @throws \InvalidArgumentException when the content has no text form
     */
    private function contentText(mixed $content): ?string
    {
        if ($content === null) {
            return null;
        }
        if (is_scalar($content) || $content instanceof \Stringable) {
            return (string)$content;
        }

        throw new \InvalidArgumentException(
            sprintf(
                'The content of a "%s" element, of type %s, has no text form.',
                $this->getElementType(),
                get_debug_type($content)
            )
        );
    }

    /**
     * @inheritDoc
     */
    abstract public function getElementClassName(): string;
}
