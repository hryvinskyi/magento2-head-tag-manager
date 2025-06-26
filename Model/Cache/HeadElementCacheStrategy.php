<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model\Cache;

use Hryvinskyi\HeadTagManager\Api\Cache\HeadElementCacheStrategyInterface;
use Hryvinskyi\HeadTagManager\Api\Serializer\HeadElementSerializerInterface;
use Magento\Framework\App\PageCache\Identifier;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;

class HeadElementCacheStrategy implements HeadElementCacheStrategyInterface
{
    private const CACHE_KEY_PREFIX = 'hryvinskyi_head_tag_manager_';
    private const CACHE_TAGS = ['hryvinskyi_head_tag_manager'];
    private const DEFAULT_CACHE_LIFETIME = 3600 * 24 * 30; // 30 days
    private bool $elementsLoaded = false;
    private array $cachedElements = [];

    public function __construct(
        private readonly FrontendInterface $cache,
        private readonly SerializerInterface $serializer,
        private readonly HeadElementSerializerInterface $elementSerializer,
        private readonly Identifier $identifier,
        private readonly LoggerInterface $logger,
        private readonly bool $enabled = true,
        private readonly int $cacheLifetime = self::DEFAULT_CACHE_LIFETIME,
        private readonly string $cacheKeyPrefix = self::CACHE_KEY_PREFIX
    ) {
    }

    /**
     * @inheritDoc
     */
    public function load(): array
    {
        if (!$this->isEnabled() || $this->elementsLoaded) {
            return $this->cachedElements;
        }

        try {
            $cacheKey = $this->getCacheKey();
            $cachedData = $this->cache->load($cacheKey);
            if ($cachedData) {
                $elementsData = $this->serializer->unserialize($cachedData);
                $this->cachedElements = $this->elementSerializer->unserialize($elementsData);

                $this->logger->debug('Head elements loaded from cache', [
                    'cache_key' => $cacheKey,
                    'elements_count' => count($this->cachedElements)
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Failed to load head elements from cache', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        $this->elementsLoaded = true;
        return $this->cachedElements;
    }

    /**
     * @inheritDoc
     */
    public function save(array $elements): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $cacheKey = $this->getCacheKey();
            $elementsData = $this->elementSerializer->serialize($elements);
            $serializedData = $this->serializer->serialize($elementsData);

            $result = $this->cache->save(
                $serializedData,
                $cacheKey,
                $this->getCacheTags(),
                $this->cacheLifetime
            );

            if ($result) {
                $this->cachedElements = $elements;
                $this->elementsLoaded = true;

                $this->logger->debug('Head elements saved to cache', [
                    'cache_key' => $cacheKey,
                    'elements_count' => count($elements)
                ]);
            }

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to save head elements to cache', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $cacheKey = $this->getCacheKey();
            $result = $this->cache->remove($cacheKey);

            if ($result) {
                $this->cachedElements = [];
                $this->elementsLoaded = false;

                $this->logger->debug('Head elements cache cleared', [
                    'cache_key' => $cacheKey
                ]);
            }

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to clear head elements cache', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @inheritDoc
     */
    public function getCacheTags(): array
    {
        return self::CACHE_TAGS;
    }

    /**
     * @inheritDoc
     */
    public function getCacheKey(): string
    {
        return $this->cacheKeyPrefix . $this->identifier->getValue();
    }
}