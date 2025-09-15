<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Plugin\Framework\Controller;

use Hryvinskyi\HeadTagManager\Api\HeadTagManagerInterface;
use Hryvinskyi\HeadTagManager\Plugin\Framework\Controller\Result;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\PageCache\Model\Config;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Test for Result plugin
 */
class ResultTest extends TestCase
{
    private Result $plugin;
    private HeadTagManagerInterface|MockObject $headTagManagerMock;
    private LoggerInterface|MockObject $loggerMock;
    private ResultInterface|MockObject $resultMock;
    private HttpResponse|MockObject $responseMock;

    protected function setUp(): void
    {
        $this->headTagManagerMock = $this->createMock(HeadTagManagerInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->resultMock = $this->createMock(ResultInterface::class);
        $this->responseMock = $this->createMock(HttpResponse::class);

        $this->plugin = new Result(
            $this->headTagManagerMock,
            $this->loggerMock
        );
    }

    /**
     * Test that empty responses are skipped
     */
    public function testAfterRenderResultSkipsEmptyResponse(): void
    {
        $this->responseMock->expects($this->once())
            ->method('getContent')
            ->willReturn('');

        $this->loggerMock->expects($this->once())
            ->method('debug')
            ->with('Skipping head tag injection for empty response');

        $this->headTagManagerMock->expects($this->never())
            ->method('render');

        $result = $this->plugin->afterRenderResult(
            $this->resultMock,
            $this->resultMock,
            $this->responseMock
        );

        $this->assertSame($this->resultMock, $result);
    }

    /**
     * Test that non-HTML responses are skipped
     */
    public function testAfterRenderResultSkipsNonHtmlResponse(): void
    {
        $this->responseMock->expects($this->once())
            ->method('getContent')
            ->willReturn('{"status": "success"}'); // JSON response

        $this->loggerMock->expects($this->once())
            ->method('debug')
            ->with('Skipping head tag injection for non-HTML response');

        $this->headTagManagerMock->expects($this->never())
            ->method('render');

        $result = $this->plugin->afterRenderResult(
            $this->resultMock,
            $this->resultMock,
            $this->responseMock
        );

        $this->assertSame($this->resultMock, $result);
    }

    /**
     * Test successful head tag injection
     */
    public function testAfterRenderResultInjectsHeadTags(): void
    {
        $originalContent = '<!DOCTYPE html><html><head><!-- {{HRYVINSKYI:PLACEHOLDER:HEAD_ADDITIONAL}} --></head><body></body></html>';
        $headContent = '<meta name="test" content="value">';
        $expectedContent = '<!DOCTYPE html><html><head><meta name="test" content="value"></head><body></body></html>';

        $this->responseMock->expects($this->exactly(2))
            ->method('getContent')
            ->willReturn($originalContent);

        $this->responseMock->expects($this->once())
            ->method('setContent')
            ->with($expectedContent);

        $this->headTagManagerMock->expects($this->once())
            ->method('render')
            ->willReturn($headContent);

        $this->loggerMock->expects($this->once())
            ->method('debug')
            ->with(
                'Head tags successfully injected into result response',
                ['tags_count' => 1]
            );

        $result = $this->plugin->afterRenderResult(
            $this->resultMock,
            $this->resultMock,
            $this->responseMock
        );

        $this->assertSame($this->resultMock, $result);
    }

    /**
     * Test that responses without placeholder are skipped
     */
    public function testAfterRenderResultSkipsResponseWithoutPlaceholder(): void
    {
        $contentWithoutPlaceholder = '<!DOCTYPE html><html><head></head><body></body></html>';

        $this->responseMock->expects($this->exactly(2))
            ->method('getContent')
            ->willReturn($contentWithoutPlaceholder);

        $this->responseMock->expects($this->never())
            ->method('setContent');

        $this->headTagManagerMock->expects($this->never())
            ->method('render');

        $this->loggerMock->expects($this->once())
            ->method('debug')
            ->with('Head placeholder not found in response content');

        $result = $this->plugin->afterRenderResult(
            $this->resultMock,
            $this->resultMock,
            $this->responseMock
        );

        $this->assertSame($this->resultMock, $result);
    }

    /**
     * Test that responses with empty head content are skipped
     */
    public function testAfterRenderResultSkipsEmptyHeadContent(): void
    {
        $originalContent = '<!DOCTYPE html><html><head><!-- {{HRYVINSKYI:PLACEHOLDER:HEAD_ADDITIONAL}} --></head><body></body></html>';

        $this->responseMock->expects($this->exactly(2))
            ->method('getContent')
            ->willReturn($originalContent);

        $this->responseMock->expects($this->never())
            ->method('setContent');

        $this->headTagManagerMock->expects($this->once())
            ->method('render')
            ->willReturn(''); // Empty head content

        $this->loggerMock->expects($this->once())
            ->method('debug')
            ->with('No head tags to inject');

        $result = $this->plugin->afterRenderResult(
            $this->resultMock,
            $this->resultMock,
            $this->responseMock
        );

        $this->assertSame($this->resultMock, $result);
    }

    /**
     * Test skip classes configuration
     */
    public function testAfterRenderResultSkipsConfiguredClasses(): void
    {
        // Create plugin with skip classes
        $pluginWithSkipClasses = new Result(
            $this->headTagManagerMock,
            $this->loggerMock,
            ['Magento\Framework\App\Response\Http']
        );

        $this->loggerMock->expects($this->once())
            ->method('debug')
            ->with(
                'Skipping head tag injection for class: ' . get_class($this->responseMock),
                ['skip_class' => 'Magento\Framework\App\Response\Http']
            );

        $this->headTagManagerMock->expects($this->never())
            ->method('render');

        $result = $pluginWithSkipClasses->afterRenderResult(
            $this->resultMock,
            $this->resultMock,
            $this->responseMock
        );

        $this->assertSame($this->resultMock, $result);
    }
}