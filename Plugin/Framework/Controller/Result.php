<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Plugin\Framework\Controller;

use Hryvinskyi\HeadTagManager\Api\HeadTagManagerInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Replaces the head placeholder of a rendered HTML page with the head elements, before the page cache stores the page
 *
 * The placeholder is always replaced, with nothing when there are no elements or rendering them failed, so neither
 * the page nor its cached copy ever carries it.
 */
class Result
{
    private const HTML_DOCTYPE_PATTERN = '<!DOCTYPE html';
    private const HTML_TAG_PATTERN = '<html';
    private const HEAD_PLACEHOLDER = '<!-- {{HRYVINSKYI:PLACEHOLDER:HEAD_ADDITIONAL}} -->';

    /**
     * @param HeadTagManagerInterface $headTagManager renders the head elements
     * @param LoggerInterface $logger records skipped responses and rendering failures
     * @param array<string,class-string> $skipClasses response classes or interfaces left untouched, by item name
     */
    public function __construct(
        private readonly HeadTagManagerInterface $headTagManager,
        private readonly LoggerInterface $logger,
        private readonly array $skipClasses = []
    ) {
    }

    /**
     * Inject the head elements into the rendered HTTP response
     *
     * @param ResultInterface $subject the intercepted result; the signature of an after plugin requires it
     * @param ResultInterface $result
     * @param ResponseInterface $response
     * @return ResultInterface
     */
    public function afterRenderResult(
        ResultInterface $subject,
        ResultInterface $result,
        ResponseInterface $response
    ): ResultInterface {
        if (!$response instanceof HttpResponse || $this->isClassSkipped($response)) {
            return $result;
        }

        $content = $this->htmlContent($response);
        if ($content === null) {
            return $result;
        }

        if (!str_contains($content, self::HEAD_PLACEHOLDER)) {
            $this->logger->debug('Head placeholder not found in response content');
            return $result;
        }

        $response->setContent(str_replace(self::HEAD_PLACEHOLDER, $this->renderHead(), $content));

        return $result;
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
            if ($response instanceof $skipClass) {
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
     * Get the response body when it is an HTML page, or null when it is empty or not HTML
     *
     * @param HttpResponse $response
     * @return string|null
     */
    private function htmlContent(HttpResponse $response): ?string
    {
        $content = $response->getContent();

        if (!is_string($content) || $content === '') {
            $this->logger->debug('Skipping head tag injection for empty response');
            return null;
        }

        if (!str_contains($content, self::HTML_DOCTYPE_PATTERN) && !str_contains($content, self::HTML_TAG_PATTERN)) {
            $this->logger->debug('Skipping head tag injection for non-HTML response');
            return null;
        }

        return $content;
    }

    /**
     * Render the head elements, or nothing when rendering fails
     *
     * @return string
     */
    private function renderHead(): string
    {
        try {
            $headContent = $this->headTagManager->render();
        } catch (Throwable $exception) {
            $this->logger->error(
                'Error while adding head elements to Result response: ' . $exception->getMessage(),
                ['exception' => $exception]
            );

            return '';
        }

        $this->logger->debug(
            $headContent === '' ? 'No head tags to inject' : 'Head tags successfully injected into result response',
            ['tags_count' => substr_count($headContent, '<')]
        );

        return $headContent;
    }
}
