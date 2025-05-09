<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model\HeadElement;

class MetaElement extends AbstractHeadElement
{
    /**
     * Render the meta element
     * @return string
     */
    public function render(): string
    {
        return '<meta' . $this->attributesToString() . '>';
    }
}