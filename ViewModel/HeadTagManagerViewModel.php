<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\ViewModel;

use Hryvinskyi\HeadTagManager\Api\HeadTagManagerInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Gives a template the head tag manager, so it can add the elements its markup needs
 */
class HeadTagManagerViewModel implements ArgumentInterface
{
    /**
     * @param HeadTagManagerInterface $headTagManager the head elements of the current request
     */
    public function __construct(private readonly HeadTagManagerInterface $headTagManager)
    {
    }

    /**
     * Get head Manager
     *
     * @return HeadTagManagerInterface
     */
    public function getManager(): HeadTagManagerInterface
    {
        return $this->headTagManager;
    }
}
