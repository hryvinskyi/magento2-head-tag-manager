<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Api\HeadTagManagerInterface;
use Hryvinskyi\HeadTagManager\Api\Registry\HeadElementFactoryRegistryInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;

/**
 * Holds the head elements of one request and renders them, lowest sort order first
 */
class HeadTagManager implements HeadTagManagerInterface
{
    /**
     * Collection of head elements, in insertion order
     *
     * @var array<string, HeadElementInterface>
     */
    protected array $elements = [];

    /**
     * @param HeadElementFactoryRegistryInterface $factoryRegistry finds the factory of an element type
     * @param SerializerInterface $serializer encodes an element's identity for its generated key
     * @param LoggerInterface $logger records an element that fails to render
     */
    public function __construct(
        private readonly HeadElementFactoryRegistryInterface $factoryRegistry,
        private readonly SerializerInterface $serializer,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function createElement(
        string $type,
        array $data = [],
        ?string $key = null
    ): HeadElementInterface {
        $factory = $this->factoryRegistry->getFactoryByType($type);
        if ($factory === null) {
            throw new \InvalidArgumentException(sprintf('No factory found for element type: %s', $type));
        }

        $element = $factory->create($data);
        $this->addElement($element, $key ?? $this->generateElementKey($type, $data));

        return $element;
    }

    /**
     * @inheritDoc
     */
    public function addElement(HeadElementInterface $element, string $key): self
    {
        $this->elements[$key] = $element;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function removeElement(string $key): self
    {
        unset($this->elements[$key]);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function hasElement(string $key): bool
    {
        return isset($this->elements[$key]);
    }

    /**
     * @inheritDoc
     */
    public function getElement(string $key): ?HeadElementInterface
    {
        return $this->elements[$key] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function addMeta(array $attributes, ?string $key = null): self
    {
        $this->createElement('meta', ['attributes' => $attributes], $key);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addMetaName(string $name, string $content, ?string $key = null): self
    {
        $data = ['attributes' => ['name' => $name, 'content' => $content]];
        $this->createElement('meta', $data, $key);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addMetaProperty(string $property, string $content, ?string $key = null): self
    {
        $data = ['attributes' => ['property' => $property, 'content' => $content]];
        $this->createElement('meta', $data, $key);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addCharset(string $charset = 'UTF-8'): self
    {
        $data = ['attributes' => ['charset' => $charset]];
        $this->createElement('meta', $data, 'charset');

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addLink(array $attributes, ?string $key = null): self
    {
        $this->createElement('link', ['attributes' => $attributes], $key);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addStylesheet(string $href, array $attributes = [], ?string $key = null): self
    {
        $data = ['attributes' => ['rel' => 'stylesheet', 'href' => $href] + $attributes];
        $this->createElement('link', $data, $key);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addInlineStyle(string $content, array $attributes = [], ?string $key = null): self
    {
        $data = ['attributes' => $attributes, 'content' => $content];
        $this->createElement('style', $data, $key);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addScript(array $attributes, ?string $content = null, ?string $key = null): self
    {
        $data = ['attributes' => $attributes, 'content' => $content];
        $this->createElement('script', $data, $key);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addExternalScript(string $src, array $attributes = [], ?string $key = null): self
    {
        $data = ['attributes' => ['src' => $src] + $attributes];
        $this->createElement('script', $data, $key);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addInlineScript(string $content, array $attributes = [], ?string $key = null): self
    {
        return $this->addScript($attributes, $content, $key);
    }

    /**
     * @inheritDoc
     */
    public function render(): string
    {
        $output = '';
        foreach ($this->renderEach() as $html) {
            $output .= $html . PHP_EOL;
        }

        return $output;
    }

    /**
     * @inheritDoc
     */
    public function getRenderedElements(): array
    {
        return $this->renderEach();
    }

    /**
     * @inheritDoc
     */
    public function generateElementKey(string $type, array $data): string
    {
        $attributes = [];
        $onlyText = true;
        foreach ($data['attributes'] ?? [] as $name => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            $value = $this->identityValue($value);
            $onlyText = $onlyText && is_string($value);
            $attributes[strtolower((string)$name)] = $value;
        }
        ksort($attributes, SORT_STRING);

        $identity = ['attributes' => $attributes];
        $content = $this->contentText($data['content'] ?? null);
        if ($content !== null && $content !== '') {
            $identity['content'] = $content;
            $onlyText = $onlyText && is_string($content);
        }

        $encoded = $this->encodedIdentity($identity, $onlyText);
        if (!is_string($encoded)) {
            throw new \InvalidArgumentException(sprintf('The identity of a "%s" element cannot be encoded.', $type));
        }

        return $type . '_' . hash('sha256', $encoded);
    }

    /**
     * @inheritDoc
     */
    public function clear(): self
    {
        $this->elements = [];
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getAllElements(): array
    {
        return $this->elements;
    }

    /**
     * Get the form of an attribute value that identifies an element: the text it renders as
     *
     * `true` renders the boolean form, which is the empty value; a number and a \Stringable compare by their text.
     * An array, which only the attribute renderer of link and meta elements writes (as data attributes or JSON),
     * is kept for the encoder as it is.
     *
     * @param mixed $value the attribute value as the caller passed it, before any element has checked its type
     * @return string|array<mixed>
     * @throws \InvalidArgumentException when the value is neither text nor an array
     */
    private function identityValue(mixed $value): string|array
    {
        if ($value === true) {
            return '';
        }
        if (is_array($value)) {
            return $value;
        }
        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string)$value;
        }

        throw new \InvalidArgumentException(
            sprintf('An attribute value of type %s cannot identify a head element.', get_debug_type($value))
        );
    }

    /**
     * Get the text content identifies an element by: a scalar or a \Stringable compares by its text
     *
     * @param mixed $content the content as the caller passed it, before any element has checked its type
     * @return mixed the text, or the value as it was when it has no text form
     */
    private function contentText(mixed $content): mixed
    {
        return is_scalar($content) || $content instanceof \Stringable ? (string)$content : $content;
    }

    /**
     * Encode an identity, with its strings made valid UTF-8
     *
     * Text that is not valid UTF-8 would make the encoder throw out of the template that adds the element. An
     * identity of text alone is encoded as it is, and only when the encoder refuses it is it scrubbed; one holding
     * an array value goes through the scrub at once, which also turns a \Stringable inside it into its text.
     *
     * @param array<string,mixed> $identity
     * @param bool $onlyText whether every attribute value and the content of the identity is a string
     * @return string|bool
     */
    private function encodedIdentity(array $identity, bool $onlyText): string|bool
    {
        if (!$onlyText) {
            return $this->serializer->serialize($this->scrubbed($identity));
        }

        try {
            return $this->serializer->serialize($identity);
        } catch (\InvalidArgumentException) {
            return $this->serializer->serialize($this->scrubbed($identity));
        }
    }

    /**
     * Get an identity whose strings are valid UTF-8, keys included, with each \Stringable as its text
     *
     * A byte that is not valid UTF-8 is replaced, so the rest of the text still tells two elements apart.
     *
     * @param array<mixed> $identity the identity, or an array value inside it
     * @return array<mixed>
     */
    private function scrubbed(array $identity): array
    {
        $scrubbed = [];
        foreach ($identity as $key => $value) {
            $scrubbed[is_string($key) ? mb_scrub($key, 'UTF-8') : $key] = $this->scrubbedValue($value);
        }

        return $scrubbed;
    }

    /**
     * Get one identity value with its strings made valid UTF-8
     *
     * @param mixed $value
     * @return mixed
     */
    private function scrubbedValue(mixed $value): mixed
    {
        if ($value instanceof \Stringable) {
            $value = (string)$value;
        }
        if (is_string($value)) {
            return mb_scrub($value, 'UTF-8');
        }
        if (is_array($value)) {
            return $this->scrubbed($value);
        }

        return $value;
    }

    /**
     * Render every element in sort order, leaving out and logging each one that fails
     *
     * The sort is stable, so elements of equal sort order keep their insertion order.
     *
     * @return array<string, string> element key => HTML
     */
    private function renderEach(): array
    {
        $elements = $this->elements;
        uasort(
            $elements,
            static fn (HeadElementInterface $a, HeadElementInterface $b): int =>
                $a->getSortOrder() <=> $b->getSortOrder()
        );

        $rendered = [];
        foreach ($elements as $key => $element) {
            try {
                $rendered[$key] = $element->render();
            } catch (\Throwable $exception) {
                $this->logger->error(
                    sprintf('Head element "%s" failed to render and was left out of the page', $key),
                    ['key' => $key, 'class' => get_class($element), 'exception' => $exception]
                );
            }
        }

        return $rendered;
    }
}
