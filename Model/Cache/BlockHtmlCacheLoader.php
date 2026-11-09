<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model\Cache;

use Hryvinskyi\HeadTagManager\Api\Cache\BlockHtmlCacheLoaderInterface;
use Hryvinskyi\HeadTagManager\Api\Cache\CachedBlockHtmlCodecInterface;
use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Api\HeadTagManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Stores a block's head elements inside its HTML cache entry, and restores them when that entry is served
 *
 * A block served from the cache does not run its code, so the elements it added when it rendered have to come back
 * with its HTML. They travel in the same entry, written by the framework's own save action under the framework's
 * lock: there is no second entry that could be missing, evicted on its own, or written after another request has
 * already read the HTML.
 *
 * The collector compares the elements before and after the block renders, nested blocks included; an element counts
 * when its key is new or holds another object than before, so a block that re-adds a key another block holds keeps
 * it. Elements the block adds after its HTML is loaded are added on a hit as on a miss, and are never stored.
 *
 * An entry that does not carry the elements in this module's signed format (written by an earlier release, while the
 * module was disabled or under another installation key, or unreadable) is answered as a miss: the framework renders
 * the block under its lock and overwrites the entry, so such an entry heals on its first use and never serves HTML
 * without its elements.
 *
 * The state of one load lives in the closures of one wrap() call, so nothing survives a block whose rendering
 * throws.
 */
class BlockHtmlCacheLoader implements BlockHtmlCacheLoaderInterface
{
    /**
     * @param CachedBlockHtmlCodecInterface $codec writes the elements into the entry and reads them back
     * @param HeadTagManagerInterface $headTagManager holds the page's head elements
     * @param LoggerInterface $logger records an entry stored without its elements or an element that is not restored
     */
    public function __construct(
        private readonly CachedBlockHtmlCodecInterface $codec,
        private readonly HeadTagManagerInterface $headTagManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function wrap(callable $loader, callable $collector, callable $saver): array
    {
        /** @var array<string, HeadElementInterface> $added */
        $added = [];

        return [
            'loader' => fn (): mixed => $this->load($loader),
            'collector' => function () use ($collector, &$added): mixed {
                $before = $this->headTagManager->getAllElements();
                $result = $collector();
                $added = $this->addedSince($before);

                return $result;
            },
            'saver' => function (mixed $data) use ($saver, &$added): void {
                $saver($this->entryFor($data, $added));
            },
        ];
    }

    /**
     * Read the entry and restore its elements; an entry without readable elements is a miss
     *
     * @param callable $loader reads the block's entry
     * @return mixed the block's HTML, or what the loader answered when it found no entry
     */
    private function load(callable $loader): mixed
    {
        $entry = $loader();
        if (!is_string($entry)) {
            return $entry;
        }

        try {
            $decoded = $this->codec->decode($entry);
            if ($decoded === null) {
                return false;
            }

            // A key of digits comes back from the stored JSON as a number; the manager keys elements by text.
            foreach ($decoded['elements'] as $key => $element) {
                $this->headTagManager->addElement($element, (string)$key);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('The head elements of a cached block could not be restored; it renders again', [
                'exception' => $e,
            ]);

            return false;
        }

        return $decoded['html'];
    }

    /**
     * The elements whose key is new since the snapshot, or holds another object than it did then
     *
     * @param array<string,HeadElementInterface> $before the elements before the block rendered
     * @return array<string, HeadElementInterface>
     */
    private function addedSince(array $before): array
    {
        $added = [];
        foreach ($this->headTagManager->getAllElements() as $key => $element) {
            if (($before[$key] ?? null) !== $element) {
                $added[$key] = $element;
            }
        }

        return $added;
    }

    /**
     * The entry the framework's save action stores for what the collector rendered
     *
     * Text is always stored with the comment that carries the elements, so the next load is a hit. A render that is
     * null, a scalar or \Stringable is stored as its text, which is what the block makes of the loader's answer
     * anyway; an array or any other object is handed on as it is. When the elements cannot be written at all, the
     * HTML is stored with an empty comment, so the block is still served from the cache; only when even that cannot
     * be signed is the plain HTML stored, and the entry renders again on its next load.
     *
     * @param mixed $data what the collector rendered
     * @param array<string,HeadElementInterface> $elements the elements the block added
     * @return mixed
     */
    private function entryFor(mixed $data, array $elements): mixed
    {
        if ($data !== null && !is_scalar($data) && !$data instanceof \Stringable) {
            return $data;
        }

        $html = (string)$data;
        try {
            return $this->codec->encode($html, $elements);
        } catch (\Throwable $e) {
            $this->logger->warning('A cached block was stored without its head elements', [
                'elements' => array_keys($elements),
                'exception' => $e,
            ]);
        }

        try {
            return $this->codec->encode($html, []);
        } catch (\Throwable $e) {
            $this->logger->error('A cached block was stored without the comment that marks its head elements', [
                'exception' => $e,
            ]);

            return $html;
        }
    }
}
