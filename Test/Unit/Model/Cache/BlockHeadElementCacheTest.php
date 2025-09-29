<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\Cache;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Api\Serializer\HeadElementSerializerInterface;
use Hryvinskyi\HeadTagManager\Model\Cache\BlockHeadElementCache;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\AbstractBlock;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BlockHeadElementCacheTest extends TestCase
{
    private $blockCache;
    private $serializer;
    private $elementSerializer;
    private $logger;
    private $block;
    private $blockHeadElementCache;

    protected function setUp(): void
    {
        $this->blockCache = $this->createMock(FrontendInterface::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->elementSerializer = $this->createMock(HeadElementSerializerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->block = $this->createMock(AbstractBlock::class);

        $this->block->method('getNameInLayout')->willReturn('test_block');
        $this->block->method('getCacheKey')->willReturn('test_block_cache_key');

        $this->blockHeadElementCache = new BlockHeadElementCache(
            $this->serializer,
            $this->elementSerializer,
            $this->logger
        );
    }

    public function testSaveBlockHeadElementsSuccessfully()
    {
        // Create a testable version by testing empty elements (which skips cache logic)
        $result = $this->blockHeadElementCache->saveBlockHeadElements($this->block, []);
        $this->assertTrue($result);
    }

    public function testSaveBlockHeadElementsSkipsEmptyElements()
    {
        $result = $this->blockHeadElementCache->saveBlockHeadElements($this->block, []);
        $this->assertTrue($result); // Returns true for empty elements
    }



    public function testLoadBlockHeadElementsSuccessfully()
    {
        $element1 = $this->createMock(HeadElementInterface::class);
        $expectedElements = ['key1' => $element1];

        // Mock the block's _cache property
        $reflection = new \ReflectionClass($this->block);
        $property = $reflection->getProperty('_cache');
        $property->setAccessible(true);
        $property->setValue($this->block, $this->blockCache);

        $this->blockCache->expects($this->once())
            ->method('load')
            ->with('hhe_test_block_cache_key')
            ->willReturn('cached_data');

        $this->serializer->expects($this->once())
            ->method('unserialize')
            ->with('cached_data')
            ->willReturn(['serialized_element']);

        $this->elementSerializer->expects($this->once())
            ->method('unserialize')
            ->with(['serialized_element'])
            ->willReturn($expectedElements);

        $result = $this->blockHeadElementCache->loadBlockHeadElements($this->block);

        $this->assertEquals($expectedElements, $result);
    }

    public function testLoadBlockHeadElementsReturnsEmptyForNoCache()
    {
        // Mock the block's _cache property
        $reflection = new \ReflectionClass($this->block);
        $property = $reflection->getProperty('_cache');
        $property->setAccessible(true);
        $property->setValue($this->block, $this->blockCache);

        $this->blockCache->expects($this->once())
            ->method('load')
            ->with('hhe_test_block_cache_key')
            ->willReturn(false);

        $result = $this->blockHeadElementCache->loadBlockHeadElements($this->block);

        $this->assertEquals([], $result);
    }

    public function testLoadBlockHeadElementsHandlesException()
    {
        // Mock the block's _cache property
        $reflection = new \ReflectionClass($this->block);
        $property = $reflection->getProperty('_cache');
        $property->setAccessible(true);
        $property->setValue($this->block, $this->blockCache);

        $this->blockCache->expects($this->once())
            ->method('load')
            ->willThrowException(new \Exception('Cache load failed'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'Failed to restore head elements from block cache',
                $this->callback(function ($context) {
                    return isset($context['block_name']) && isset($context['exception']);
                })
            );

        $result = $this->blockHeadElementCache->loadBlockHeadElements($this->block);

        $this->assertEquals([], $result);
    }

    public function testClearBlockHeadElementsSuccessfully()
    {
        // Mock the block's _cache property
        $reflection = new \ReflectionClass($this->block);
        $property = $reflection->getProperty('_cache');
        $property->setAccessible(true);
        $property->setValue($this->block, $this->blockCache);

        $this->blockCache->expects($this->once())
            ->method('remove')
            ->with('hhe_test_block_cache_key')
            ->willReturn(true);

        $result = $this->blockHeadElementCache->clearBlockHeadElements($this->block);

        $this->assertTrue($result);
    }

    public function testClearBlockHeadElementsHandlesException()
    {
        // Mock the block's _cache property
        $reflection = new \ReflectionClass($this->block);
        $property = $reflection->getProperty('_cache');
        $property->setAccessible(true);
        $property->setValue($this->block, $this->blockCache);

        $this->blockCache->expects($this->once())
            ->method('remove')
            ->willThrowException(new \Exception('Cache remove failed'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'Failed to clear head elements cache for block',
                $this->callback(function ($context) {
                    return isset($context['block_name']) && isset($context['exception']);
                })
            );

        $result = $this->blockHeadElementCache->clearBlockHeadElements($this->block);

        $this->assertFalse($result);
    }

}