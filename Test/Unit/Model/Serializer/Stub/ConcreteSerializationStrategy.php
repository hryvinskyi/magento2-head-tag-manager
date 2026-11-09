<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\Serializer\Stub;

use Hryvinskyi\HeadTagManager\Model\Serializer\Strategy\AbstractSerializationStrategy;

/**
 * A strategy for the test element that adds nothing to the serialized data
 */
class ConcreteSerializationStrategy extends AbstractSerializationStrategy
{
    /**
     * @inheritDoc
     */
    public function getElementType(): string
    {
        return 'test';
    }

    /**
     * @inheritDoc
     */
    public function getElementClassName(): string
    {
        return TestElement::class;
    }
}
