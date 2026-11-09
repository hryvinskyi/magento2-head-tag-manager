<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Plugin\Framework\Controller;

use Hryvinskyi\HeadTagManager\Api\HeadTagManagerInterface;
use Hryvinskyi\HeadTagManager\Plugin\Framework\Controller\Result;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\Response\HttpInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ResultTest extends TestCase
{
    private const PLACEHOLDER = '<!-- {{HRYVINSKYI:PLACEHOLDER:HEAD_ADDITIONAL}} -->';
    private const PAGE = '<!DOCTYPE html><html><head>' . self::PLACEHOLDER . '</head><body></body></html>';
    private const EMPTY_HEAD_PAGE = '<!DOCTYPE html><html><head></head><body></body></html>';

    /**
     * @var Result
     */
    private Result $plugin;

    /**
     * @var HeadTagManagerInterface&MockObject
     */
    private MockObject $headTagManager;

    /**
     * @var LoggerInterface&MockObject
     */
    private MockObject $logger;

    /**
     * @var ResultInterface&MockObject
     */
    private MockObject $result;

    /**
     * @var HttpResponse&MockObject
     */
    private MockObject $response;

    /**
     * @var string|null the content the plugin wrote into the response, null while it wrote nothing
     */
    private ?string $writtenContent = null;

    protected function setUp(): void
    {
        $this->headTagManager = $this->createMock(HeadTagManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->result = $this->createMock(ResultInterface::class);
        $this->response = $this->createMock(HttpResponse::class);
        $this->response->method('setContent')->willReturnCallback(function (string $content): HttpResponse {
            $this->writtenContent = $content;

            return $this->response;
        });

        $this->plugin = new Result($this->headTagManager, $this->logger);
    }

    public function testSkipsAnEmptyResponse(): void
    {
        $this->response->method('getContent')->willReturn('');
        $this->headTagManager->expects($this->never())->method('render');

        $this->assertSame($this->result, $this->afterRenderResult());
        $this->assertNull($this->writtenContent);
    }

    public function testSkipsANonHtmlResponse(): void
    {
        $this->response->method('getContent')->willReturn('{"status": "success"}');
        $this->headTagManager->expects($this->never())->method('render');

        $this->assertSame($this->result, $this->afterRenderResult());
        $this->assertNull($this->writtenContent);
    }

    public function testInjectsTheHeadTagsInPlaceOfThePlaceholder(): void
    {
        $this->response->method('getContent')->willReturn(self::PAGE);
        $this->headTagManager->method('render')->willReturn('<meta name="test" content="value">');

        $this->assertSame($this->result, $this->afterRenderResult());
        $this->assertSame(
            '<!DOCTYPE html><html><head><meta name="test" content="value"></head><body></body></html>',
            $this->writtenContent
        );
    }

    public function testLeavesAResponseWithoutThePlaceholderUntouched(): void
    {
        $this->response->method('getContent')->willReturn(self::EMPTY_HEAD_PAGE);
        $this->headTagManager->expects($this->never())->method('render');

        $this->assertSame($this->result, $this->afterRenderResult());
        $this->assertNull($this->writtenContent);
    }

    /**
     * With nothing to inject the placeholder is still removed, so the page (and its cached copy) never shows it
     */
    public function testRemovesThePlaceholderWhenThereIsNothingToInject(): void
    {
        $this->response->method('getContent')->willReturn(self::PAGE);
        $this->headTagManager->method('render')->willReturn('');

        $this->assertSame($this->result, $this->afterRenderResult());
        $this->assertSame(self::EMPTY_HEAD_PAGE, $this->writtenContent);
    }

    /**
     * A failure while rendering is logged and the placeholder is removed all the same
     */
    public function testRemovesThePlaceholderWhenRenderingFails(): void
    {
        $failure = new \RuntimeException('render failed');
        $this->response->method('getContent')->willReturn(self::PAGE);
        $this->headTagManager->method('render')->willThrowException($failure);
        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('render failed'),
                $this->callback(static fn (array $context): bool => $context['exception'] === $failure)
            );

        $this->assertSame($this->result, $this->afterRenderResult());
        $this->assertSame(self::EMPTY_HEAD_PAGE, $this->writtenContent);
    }

    public function testSkipsAConfiguredResponseClass(): void
    {
        $plugin = new Result($this->headTagManager, $this->logger, ['http' => HttpInterface::class]);
        $this->response->method('getContent')->willReturn(self::PAGE);
        $this->headTagManager->expects($this->never())->method('render');

        $this->assertSame($this->result, $plugin->afterRenderResult($this->result, $this->result, $this->response));
        $this->assertNull($this->writtenContent);
    }

    /**
     * A response that is not an HTTP response has no body to inject into and is passed through
     */
    public function testPassesThroughAResponseThatIsNotHttp(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $this->headTagManager->expects($this->never())->method('render');

        $this->assertSame($this->result, $this->plugin->afterRenderResult($this->result, $this->result, $response));
    }

    /**
     * Runs the plugin on the HTTP response double
     *
     * @return ResultInterface
     */
    private function afterRenderResult(): ResultInterface
    {
        return $this->plugin->afterRenderResult($this->result, $this->result, $this->response);
    }
}
