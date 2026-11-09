<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model\Cache;

use Hryvinskyi\HeadTagManager\Api\Cache\CachedBlockHtmlCodecInterface;
use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Api\Serializer\HeadElementSerializerInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;

/**
 * Carries a block's head elements at the end of its HTML cache entry, in one signed HTML comment
 *
 * The comment is `<!--hryvinskyi-head-elements:1:`, the signature, `:`, the base64 of the JSON the element
 * serializer produces, then `-->`. The JSON keeps slashes and non-ASCII text as they are, since base64 already keeps
 * `--` and `-->` out of the comment whatever the elements contain. The `1` is the format's version: a later format
 * changes it, and the entries of this one are then no longer read. Should the entry ever be printed without this
 * module, the comment shows nothing in an HTML page.
 *
 * The signature is the first 128 bits, in hex, of the installation's keyed hash of the base64 payload, so only a
 * comment this module wrote on this installation is read. HTML that merely ends with text of the same shape (an
 * entry written while the module was disabled, ending with imported or editor content) carries no valid signature
 * and is not taken for elements, which would otherwise come back as scripts and stylesheets of the page. A change
 * of the installation's key makes every entry unreadable once; each is rendered again and rewritten.
 *
 * Only a comment at the very end of the entry counts. A payload is a JSON object keyed by element key, or `[]` for
 * no elements; anything else is unreadable.
 */
class CachedBlockHtmlCodec implements CachedBlockHtmlCodecInterface
{
    private const MARKER_START = '<!--hryvinskyi-head-elements:1:';
    private const MARKER_END = '-->';
    private const SIGNATURE_SEPARATOR = ':';
    private const BASE64_PATTERN = '~^[A-Za-z0-9+/]*={0,2}$~';
    private const SIGNATURE_LENGTH = 32;
    private const JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /**
     * @param HeadElementSerializerInterface $elementSerializer turns the elements into plain data and back
     * @param SerializerInterface $base64JsonSerializer decodes the comment's base64 JSON payload
     * @param EncryptorInterface $encryptor signs the payload with the installation's key
     * @param LoggerInterface $logger records an element left out of the entry and a comment that cannot be read
     */
    public function __construct(
        private readonly HeadElementSerializerInterface $elementSerializer,
        private readonly SerializerInterface $base64JsonSerializer,
        private readonly EncryptorInterface $encryptor,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function encode(string $html, array $elements): string
    {
        $payload = base64_encode($this->json($this->elementSerializer->serialize($elements)));

        return $html . self::MARKER_START . $this->signature($payload) . self::SIGNATURE_SEPARATOR . $payload
            . self::MARKER_END;
    }

    /**
     * @inheritDoc
     */
    public function decode(string $entry): ?array
    {
        $start = strrpos($entry, self::MARKER_START);
        if ($start === false || !str_ends_with($entry, self::MARKER_END)) {
            return null;
        }

        $commentStart = $start + strlen(self::MARKER_START);
        $comment = substr($entry, $commentStart, strlen($entry) - $commentStart - strlen(self::MARKER_END));
        $separator = strpos($comment, self::SIGNATURE_SEPARATOR);
        if ($separator === false) {
            return null;
        }

        $signature = substr($comment, 0, $separator);
        $payload = substr($comment, $separator + 1);
        if (!hash_equals($this->signature($payload), $signature)) {
            return null;
        }

        $data = $this->payloadData($payload);
        if ($data === null) {
            $this->logger->warning('A block HTML cache entry carries head elements that cannot be read', [
                'payload' => substr($payload, 0, 200),
            ]);

            return null;
        }

        return [
            'html' => substr($entry, 0, $start),
            'elements' => $this->elementSerializer->unserialize($data),
        ];
    }

    /**
     * The signature of a payload: the start of the installation's keyed hash of it
     *
     * @param string $payload
     * @return string
     * @throws \RuntimeException when the installation has no key
     */
    private function signature(string $payload): string
    {
        return substr($this->encryptor->hash($payload), 0, self::SIGNATURE_LENGTH);
    }

    /**
     * The JSON of the elements' serialized data, without each element whose data JSON rejects
     *
     * The element serializer has already left out, and logged, an element it cannot serialize. The data is encoded
     * at once; only when that fails is each element tried on its own, and one whose data JSON rejects (text that is
     * not UTF-8, cut in the middle of a character for instance) is left out with a warning, so the other elements are
     * still stored.
     *
     * @param array<int|string,array<string,mixed>> $elementsData the serialized data of the elements, by key
     * @return string
     */
    private function json(array $elementsData): string
    {
        try {
            return json_encode($elementsData, self::JSON_FLAGS);
        } catch (\JsonException) {
            $encodable = [];
            foreach ($elementsData as $key => $data) {
                try {
                    json_encode([$key => $data], self::JSON_FLAGS);
                } catch (\JsonException $e) {
                    $this->logger->warning('A head element cannot be stored with its cached block and is left out', [
                        'key' => $key,
                        'exception' => $e->getMessage(),
                    ]);
                    continue;
                }
                $encodable[$key] = $data;
            }

            return json_encode($encodable, self::JSON_FLAGS);
        }
    }

    /**
     * The element data a signed payload carries, or null when it is not base64 of a JSON object or `[]`
     *
     * The payload must be strict base64. The framework's base64 JSON decoder would otherwise ignore stray characters
     * and fall back to reading the text as raw JSON; the signature already refuses any payload this module did not
     * write, and this keeps a payload that is not base64 from ever being read.
     *
     * @param string $payload
     * @return array<int|string, mixed>|null
     */
    private function payloadData(string $payload): ?array
    {
        if (!preg_match(self::BASE64_PATTERN, $payload) || strlen($payload) % 4 !== 0) {
            return null;
        }

        $data = $this->base64JsonSerializer->unserialize($payload);

        return is_array($data) ? $data : null;
    }
}
