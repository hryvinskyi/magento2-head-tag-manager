<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model\Cache;

use Hryvinskyi\HeadTagManager\Api\Cache\BlockHeadElementCacheInterface;
use Hryvinskyi\HeadTagManager\Api\Serializer\HeadElementSerializerInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\RuntimeException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\AbstractBlock;
use Psr\Log\LoggerInterface;

/**
 * Service for caching block-specific head elements
 */
class BlockHeadElementCache implements BlockHeadElementCacheInterface
{
    private const CACHE_KEY_PREFIX = 'hryvinskyi_head_elements_block_';
    private const CACHE_TAGS = ['hryvinskyi_head_tag_manager_block'];
    private const CACHE_LIFETIME = 3600 * 24 * 30; // 30 days

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly SerializerInterface $serializer,
        private readonly HeadElementSerializerInterface $elementSerializer,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function saveBlockHeadElements(AbstractBlock $block, array $headElements): bool
    {
        if (empty($headElements)) {
            return true;
        }

        try {
            $cacheKey = $this->generateCacheKey($block);
            $serializedData = $this->serializer->serialize($headElements);

            $result = $this->cache->save(
                $serializedData,
                $cacheKey,
                array_merge(self::CACHE_TAGS, $this->generateCacheTags($block)),
                self::CACHE_LIFETIME
            );

            if ($result) {
                $this->logger->debug('Saved head elements to block cache', [
                    'block_name' => $block->getNameInLayout(),
                    'elements_count' => count($headElements),
                    'cache_key' => $cacheKey
                ]);
            }

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to save head elements to block cache', [
                'block_name' => $block->getNameInLayout(),
                'exception' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function loadBlockHeadElements(AbstractBlock $block): array
    {
        try {
            $cacheKey = $this->generateCacheKey($block);
            $cachedData = $this->cache->load($cacheKey);

            if ($cachedData) {
                $headElementsData = $this->serializer->unserialize($cachedData);
                $headElementsData = $this->elementSerializer->unserialize($headElementsData);

                $this->logger->debug('Restored head elements from block cache', [
                    'block_name' => $block->getNameInLayout(),
                    'elements_count' => count($headElementsData),
                    'cache_key' => $cacheKey
                ]);

                return $headElementsData;
            }

            return [];
        } catch (\Throwable $e) {
            $this->logger->error('Failed to restore head elements from block cache', [
                'block_name' => $block->getNameInLayout(),
                'exception' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * @inheritDoc
     */
    public function clearBlockHeadElements(AbstractBlock $block): bool
    {
        try {
            $cacheKey = $this->generateCacheKey($block);
            $result = $this->cache->remove($cacheKey);

            if ($result) {
                $this->logger->debug('Cleared head elements cache for block', [
                    'block_name' => $block->getNameInLayout(),
                    'cache_key' => $cacheKey
                ]);
            }

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to clear head elements cache for block', [
                'block_name' => $block->getNameInLayout(),
                'exception' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Generate cache key for block-specific head elements
     *
     * @param AbstractBlock $block
     * @return string
     * @throws RuntimeException
     */
    private function generateCacheKey(AbstractBlock $block): string
    {
        // Use the block's own cache key to ensure proper cache invalidation
        $blockCacheKey = $block->getCacheKey();
        return self::CACHE_KEY_PREFIX . md5($blockCacheKey);
    }

    /**
     * Generate cache tags for block-specific head elements
     *
     * @param AbstractBlock $block
     * @return array
     * @throws \ReflectionException
     */
    private function generateCacheTags(AbstractBlock $block): array
    {
        $reflection = new \ReflectionClass($block);
        $method = $reflection->getMethod('getCacheTags');
        $method->setAccessible(true);
        return $method->invoke($block);
    }
}
