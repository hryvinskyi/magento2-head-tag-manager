<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Plugin\Framework\Controller;

use Hryvinskyi\HeadTagManager\Api\HeadTagManagerInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Plugin for injecting head tags into Result renderResult - works before page cache
 */
class Result
{
    private const HTML_DOCTYPE_PATTERN = '<!DOCTYPE html';
    private const HTML_TAG_PATTERN = '<html';
    private const HEAD_PLACEHOLDER = '<!-- {{HRYVINSKYI:PLACEHOLDER:HEAD_ADDITIONAL}} -->';

    public function __construct(
        private readonly HeadTagManagerInterface $headTagManager,
        private readonly LoggerInterface $logger,
        private readonly array $skipClasses = []
    ) {
    }

    /**
     * Process head tag injection before result is cached
     *
     * @param ResultInterface $subject
     * @param ResultInterface $result
     * @param HttpResponse $response
     * @return ResultInterface
     */
    public function afterRenderResult(
        ResultInterface $subject,
        ResultInterface $result,
        ResponseInterface $response
    ): ResultInterface {
        if ($this->shouldSkipProcessing($response)) {
            return $result;
        }

        try {
            $this->processResponse($response);
        } catch (Throwable $e) {
            $this->handleError($e);
        }

        return $result;
    }

    /**
     * Check if processing should be skipped for the given response
     *
     * @param HttpResponse $response
     * @return bool
     */
    private function shouldSkipProcessing(HttpResponse $response): bool
    {
        return $this->isClassSkipped($response)
            || !$this->isHtmlResponse($response);
    }

    /**
     * Check if the response class should be skipped
     *
     * @param HttpResponse $response
     * @return bool
     */
    private function isClassSkipped(HttpResponse $response): bool
    {
        foreach ($this->skipClasses as $skipClass) {
            if ($response instanceof $skipClass || $this->implementsInterface($response, $skipClass)) {
                $this->logger->debug(
                    'Skipping head tag injection for class: ' . get_class($response),
                    ['skip_class' => $skipClass]
                );
                return true;
            }
        }

        return false;
    }

    /**
     * Check if subject implements the given interface
     *
     * @param object $subject
     * @param string $interface
     * @return bool
     */
    private function implementsInterface(object $subject, string $interface): bool
    {
        return in_array($interface, class_implements($subject) ?: [], true);
    }

    /**
     * Validate if the response content is HTML
     *
     * @param HttpResponse $response
     * @return bool
     */
    private function isHtmlResponse(HttpResponse $response): bool
    {
        $content = $response->getContent();

        if (empty($content)) {
            $this->logger->debug('Skipping head tag injection for empty response');
            return false;
        }

        $isHtml = str_contains($content, self::HTML_DOCTYPE_PATTERN) ||
            str_contains($content, self::HTML_TAG_PATTERN);

        if (!$isHtml) {
            $this->logger->debug('Skipping head tag injection for non-HTML response');
        }

        return $isHtml;
    }

    /**
     * Process the HTTP response by injecting head tags
     *
     * @param HttpResponse $response
     * @return void
     */
    private function processResponse(HttpResponse $response): void
    {
        $content = $response->getContent();

        if (!str_contains($content, self::HEAD_PLACEHOLDER)) {
            $this->logger->debug('Head placeholder not found in response content');
            return;
        }

        $headContent = $this->headTagManager->render();

        if (empty($headContent)) {
            $this->logger->debug('No head tags to inject');
            return;
        }

        $updatedContent = str_replace(self::HEAD_PLACEHOLDER, $headContent, $content);
        $response->setContent($updatedContent);

        $this->logger->debug(
            'Head tags successfully injected into result response',
            ['tags_count' => substr_count($headContent, '<')]
        );
    }

    /**
     * Handle errors that occur during processing
     *
     * @param Throwable $exception
     * @return void
     */
    private function handleError(Throwable $exception): void
    {
        $this->logger->error(
            'Error while adding head elements to Result response: ' . $exception->getMessage(),
            [
                'exception' => $exception,
                'trace' => $exception->getTraceAsString()
            ]
        );
    }
}