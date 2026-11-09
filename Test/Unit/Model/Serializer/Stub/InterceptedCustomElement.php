<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\Serializer\Stub;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;

/**
 * A custom element a module registers a factory for; the object manager hands out its generated interceptor
 */
class InterceptedCustomElement implements HeadElementInterface
{
    /**
     * @inheritDoc
     */
    public function render(): string
    {
        return '<custom>';
    }

    /**
     * @inheritDoc
     */
    public function getAttributes(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getSortOrder(): int
    {
        return 5;
    }
}
