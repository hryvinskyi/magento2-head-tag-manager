<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Plugin\Framework\App\Response;

use Hryvinskyi\HeadTagManager\Api\HeadTagManagerInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Plugin for injecting head tags into HTTP responses
 */
class Http
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
     * Inject head elements into HTTP response before sending
     *
     * @param HttpResponse $subject
     * @return void
     */
    public function beforeSendResponse(HttpResponse $subject): void
    {
        if ($this->shouldSkipProcessing($subject)) {
            return;
        }

        try {
            $this->processResponse($subject);
        } catch (Throwable $e) {
            $this->handleError($e);
        }
    }

    /**
     * Check if processing should be skipped for the given response
     *
     * @param HttpResponse $subject
     * @return bool
     */
    private function shouldSkipProcessing(HttpResponse $subject): bool
    {
        return $this->isClassSkipped($subject) || !$this->isHtmlResponse($subject);
    }

    /**
     * Check if the response class should be skipped
     *
     * @param HttpResponse $subject
     * @return bool
     */
    private function isClassSkipped(HttpResponse $subject): bool
    {
        foreach ($this->skipClasses as $skipClass) {
            if ($subject instanceof $skipClass || $this->implementsInterface($subject, $skipClass)) {
                $this->logger->debug(
                    'Skipping head tag injection for class: ' . get_class($subject),
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
     * @param HttpResponse $subject
     * @return bool
     */
    private function isHtmlResponse(HttpResponse $subject): bool
    {
        $content = $subject->getContent();

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
     * @param HttpResponse $subject
     * @return void
     */
    private function processResponse(HttpResponse $subject): void
    {
        $content = $subject->getContent();

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
        $subject->setContent($updatedContent);
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
            'Error while adding head elements to response: ' . $exception->getMessage(),
            [
                'exception' => $exception,
                'trace' => $exception->getTraceAsString()
            ]
        );
    }
}