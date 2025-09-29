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
    private const CACHE_TAG = 'HEAD_ELEMENT_BLOCK';
    private const DEFAULT_CACHE_LIFETIME = 86400; // 1 day

    public function __construct(
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
            // Get block's own cache instance
            $blockCache = $this->getBlockCache($block);
            if (!$blockCache) {
                $this->logger->debug('Block cache not available, skipping head elements cache', [
                    'block_name' => $block->getNameInLayout()
                ]);
                return true;
            }

            $cacheKey = $this->generateCacheKey($block);
            $cacheTags = $this->getBlockCacheTags($block);
            $serializedData = $this->serializer->serialize($headElements);

            // Use block's cache lifetime to ensure head elements cache expires with the block cache
            // This prevents cache mismatch issues
            $effectiveLifetime = is_numeric($cacheLifetime) ? (int)$cacheLifetime : self::DEFAULT_CACHE_LIFETIME;

            // Add our cache tag to block's tags
            $allCacheTags = array_merge($cacheTags, [self::CACHE_TAG]);

            $result = $blockCache->save(
                $serializedData,
                $cacheKey,
                $allCacheTags,
                $effectiveLifetime
            );

            if ($result) {
                $this->logger->debug('Saved head elements to block cache', [
                    'block_name' => $block->getNameInLayout(),
                    'elements_count' => count($headElements),
                    'cache_key' => $cacheKey,
                    'block_cache_lifetime' => $cacheLifetime,
                    'head_elements_cache_lifetime' => $effectiveLifetime
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
            // Get block's own cache instance
            $blockCache = $this->getBlockCache($block);
            if (!$blockCache) {
                return [];
            }

            $cacheKey = $this->generateCacheKey($block);
            $cachedData = $blockCache->load($cacheKey);

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
            // Get block's own cache instance
            $blockCache = $this->getBlockCache($block);
            if (!$blockCache) {
                return true; // Consider success if no cache available
            }

            $cacheKey = $this->generateCacheKey($block);
            $result = $blockCache->remove($cacheKey);

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

    /**
     * Get block's own cache frontend instance from _cache property
     *
     * @param AbstractBlock $block
     * @return \Magento\Framework\Cache\FrontendInterface|null
     * @throws \ReflectionException
     */
    private function getBlockCache(AbstractBlock $block): ?\Magento\Framework\Cache\FrontendInterface
    {
        $reflection = new \ReflectionClass($block);
        $property = $reflection->getProperty('_cache');
        $property->setAccessible(true);

        return $property->getValue($block);
    }
}
