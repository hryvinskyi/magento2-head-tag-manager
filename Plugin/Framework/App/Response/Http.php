<?php

/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

namespace Hryvinskyi\HeadTagManager\Plugin\Framework\App\Response;

use Hryvinskyi\HeadTagManager\Api\HeadTagManagerInterface;
use Psr\Log\LoggerInterface;

class Http
{
    public function __construct(
        private readonly HeadTagManagerInterface $headTagManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Add head elements to the response
     *
     * @param \Magento\Framework\App\Response\Http $subject
     * @return void
     */
    public function beforeSendResponse(\Magento\Framework\App\Response\Http $subject): void
    {
        try {
            $content = $subject->getContent();
            $content = str_replace(
                '<!-- {{HRYVINSKYI:PLACEHOLDER:HEAD_ADDITIONAL}} -->',
                $this->headTagManager->render(),
                $content
            );
            $subject->setContent($content);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Error while adding head elements to response: ' . $e->getMessage() . $e->getTraceAsString()
            );
        }
    }
}
