<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\Serializer\Stub;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Model\Serializer\Strategy\AbstractSerializationStrategy;

/**
 * A strategy for the test element that contributes extra serialized data
 */
class ConcreteSerializationStrategyWithAdditionalData extends AbstractSerializationStrategy
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

    /**
     * @inheritDoc
     *
     * @return array<string,string>
     */
    protected function getAdditionalSerializationData(HeadElementInterface $element): array
    {
        return ['additional' => 'data'];
    }
}
