<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Plugin\Framework\App\Response;

use Hryvinskyi\HeadTagManager\Api\HeadTagManagerInterface;
use Hryvinskyi\HeadTagManager\Plugin\Framework\App\Response\Http;
use Magento\Framework\App\Response\Http as MagentoHttp;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class HttpTest extends TestCase
{
    private $headTagManager;
    private $logger;
    private $http;
    private $response;

    protected function setUp(): void
    {
        $this->headTagManager = $this->createMock(HeadTagManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->http = new Http($this->headTagManager, $this->logger);
        $this->response = $this->createMock(MagentoHttp::class);
    }

    public function testReplacesPlaceholderWithHeadTagsInResponse()
    {
        $originalContent = '<html><head><!-- {{HRYVINSKYI:PLACEHOLDER:HEAD_ADDITIONAL}} --></head><body>Content</body></html>';
        $headTags = '<meta name="description" content="Test">';
        $expectedContent = '<html><head><meta name="description" content="Test"></head><body>Content</body></html>';

        $this->response->expects($this->once())
            ->method('getContent')
            ->willReturn($originalContent);

        $this->headTagManager->expects($this->once())
            ->method('render')
            ->willReturn($headTags);

        $this->response->expects($this->once())
            ->method('setContent')
            ->with($expectedContent);

        $this->http->beforeSendResponse($this->response);
    }

    public function testPreservesOriginalContentWhenNoPlaceholderExists()
    {
        $originalContent = '<html><head></head><body>Content</body></html>';

        $this->response->expects($this->once())
            ->method('getContent')
            ->willReturn($originalContent);

        $this->headTagManager->expects($this->once())
            ->method('render')
            ->willReturn('<meta name="description" content="Test">');

        $this->response->expects($this->once())
            ->method('setContent')
            ->with($originalContent);

        $this->http->beforeSendResponse($this->response);
    }

    public function testLogsErrorWhenExceptionIsThrown()
    {
        $this->response->expects($this->once())
            ->method('getContent')
            ->willThrowException(new \Exception('Test exception'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Error while adding head elements to response: Test exception'));

        $this->http->beforeSendResponse($this->response);
    }
}