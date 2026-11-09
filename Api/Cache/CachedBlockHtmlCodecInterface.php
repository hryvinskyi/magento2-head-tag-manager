<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Api\Cache;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;

/**
 * Writes a block's head elements into its block HTML cache entry, and splits such an entry back apart
 *
 * The elements travel at the very end of the entry, in one HTML comment that names the format's version and is
 * signed with the installation's key. An entry that does not end with a readable comment of this version, signed on
 * this installation, is not one this module wrote for the current format.
 */
interface CachedBlockHtmlCodecInterface
{
    /**
     * The entry to store for the block's HTML and the head elements it added
     *
     * The comment is always written, also when there are no elements, so the entry is recognised as complete. An
     * element that cannot be serialized or encoded is left out of it with a warning naming its key; the others are
     * stored.
     *
     * @param string $html the block's HTML
     * @param array<string,HeadElementInterface> $elements the elements the block added, by their key in the manager
     * @return string the HTML followed by the comment that carries the elements
     * @throws \RuntimeException when the installation has no key to sign the comment with
     */
    public function encode(string $html, array $elements): string;

    /**
     * Split an entry into the block's HTML and its head elements
     *
     * An element that can no longer be recreated is left out of a readable entry; the others still come back.
     *
     * @param string $entry the stored entry
     * @return array{html: string, elements: array<string, HeadElementInterface>}|null null when the entry does not
     *     end with a comment of this format signed on this installation, or the comment cannot be read
     * @throws \RuntimeException when the installation has no key to check the signature with
     */
    public function decode(string $entry): ?array;
}
