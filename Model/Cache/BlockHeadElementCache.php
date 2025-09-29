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
use Magento\Framework\Exception\RuntimeException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\AbstractBlock;
use Psr\Log\LoggerInterface;

/**
 * Service for caching block-specific head elements
 */
class BlockHeadElementCache implements BlockHeadElementCacheInterface
{
    private const CACHE_KEY_PREFIX = 'hhe_';

    public function __construct(
        private readonly Type $cache,
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

        // Skip caching if block doesn't have cache lifetime set
        $cacheLifetime = $this->getBlockCacheLifetime($block);
        if ($cacheLifetime === null || $cacheLifetime === false) {
            $this->logger->debug('Skipping head elements cache - block not cacheable', [
                'block_name' => $block->getNameInLayout()
            ]);
            return true;
        }

        try {
            $cacheKey = $this->generateCacheKey($block);
            $cacheTags = $this->getBlockCacheTags($block);
            $serializedData = $this->serializer->serialize($headElements);

            // Head tag manager cache should never expire on its own
            // It should only be rewritten when the block is actually rendered
            // This prevents cache mismatch issues where head elements expire before block cache
            $result = $this->cache->save(
                $serializedData,
                $cacheKey,
                $cacheTags,
                Type::CACHE_LIFETIME // Use module's long cache lifetime (1 year)
            );

            if ($result) {
                $this->logger->debug('Saved head elements to block cache', [
                    'block_name' => $block->getNameInLayout(),
                    'elements_count' => count($headElements),
                    'cache_key' => $cacheKey,
                    'block_cache_lifetime' => $cacheLifetime,
                    'head_elements_cache_lifetime' => Type::CACHE_LIFETIME
                ]);
            } else {
                $this->logger->warning('Failed to save head elements to block cache', [
                    'block_name' => $block->getNameInLayout(),
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
     * Generate cache key for block-specific head elements using block's own getCacheKey
     *
     * @param AbstractBlock $block
     * @return string
     * @throws RuntimeException
     */
    private function generateCacheKey(AbstractBlock $block): string
    {
        // Use the block's own cache key with our prefix
        return self::CACHE_KEY_PREFIX . $block->getCacheKey();
    }

    /**
     * Get cache tags from block's own getCacheTags method
     *
     * @param AbstractBlock $block
     * @return array
     * @throws \ReflectionException
     */
    private function getBlockCacheTags(AbstractBlock $block): array
    {
        $reflection = new \ReflectionClass($block);
        $method = $reflection->getMethod('getCacheTags');
        $method->setAccessible(true);

        return $method->invoke($block);
    }

    /**
     * Get cache lifetime from block's own getCacheLifetime method
     *
     * @param AbstractBlock $block
     * @return int|bool|null
     * @throws \ReflectionException
     */
    private function getBlockCacheLifetime(AbstractBlock $block)
    {
        $reflection = new \ReflectionClass($block);
        $method = $reflection->getMethod('getCacheLifetime');
        $method->setAccessible(true);

        return $method->invoke($block);
    }
}
