<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\Cache\Fixture;

use Hryvinskyi\HeadTagManager\Model\HeadElement\AbstractHeadElement;

/**
 * A head element of a type a third party registers a factory for, with no serialization strategy of its own
 */
class CustomHeadElement extends AbstractHeadElement
{
    /**
     * Render the element as a tag of its own name, its attributes in the order they were set
     *
     * @return string
     */
    public function render(): string
    {
        $html = '<custom';
        foreach ($this->getTextAttributes() as $name => $text) {
            $html .= ' ' . $name . '="' . htmlspecialchars($text, ENT_QUOTES) . '"';
        }

        return $html . '>';
    }
}
