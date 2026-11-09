<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model\HeadElement;

/**
 * Declaration of the factory Magento generates for StyleElement (`setup:di:compile` writes it to
 * `generated/code`), so static analysis runs on an installation that has not been compiled. Loaded through
 * `scanFiles`, which reads the declaration without executing it.
 */
class StyleElementFactory
{
    /**
     * Create the element
     *
     * @param array<string,mixed> $data constructor arguments of the element
     * @return StyleElement
     */
    public function create(array $data = []): StyleElement
    {
        throw new \BadMethodCallException('Declaration for static analysis only.');
    }
}
