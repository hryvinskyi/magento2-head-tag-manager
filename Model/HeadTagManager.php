<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Model;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Api\HeadTagManagerInterface;
use Hryvinskyi\HeadTagManager\Model\HeadElement\LinkElementFactory;
use Hryvinskyi\HeadTagManager\Model\HeadElement\MetaElementFactory;
use Hryvinskyi\HeadTagManager\Model\HeadElement\ScriptElementFactory;
use Hryvinskyi\HeadTagManager\Model\HeadElement\StyleElementFactory;

class HeadTagManager implements HeadTagManagerInterface
{
    /**
     * Collection of head elements
     * @var HeadElementInterface[]
     */
    protected array $elements = [];

    /**
     * Create a new head manager
     */
    public function __construct(
        private readonly LinkElementFactory $linkElementFactory,
        private readonly MetaElementFactory $metaElementFactory,
        private readonly ScriptElementFactory $scriptElementFactory,
        private readonly StyleElementFactory $styleElementFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function addElement(HeadElementInterface $element, ?string $key = null): self
    {
        if ($key !== null) {
            $this->elements[$key] = $element;
        } else {
            $this->elements[] = $element;
        }
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
        return $this->addElement($this->metaElementFactory->create(['attributes' => $attributes]), $key);
    }

    /**
     * @inheritDoc
     */
    public function addMetaName(string $name, string $content, ?string $key = null): self
    {
        $metaKey = $key ?? 'meta_' . $name;
        $metaElement = $this->metaElementFactory->create(['attributes' => ['name' => $name, 'content' => $content]]);
        return $this->addElement($metaElement, $metaKey);
    }

    /**
     * @inheritDoc
     */
    public function addMetaProperty(string $property, string $content, ?string $key = null): self
    {
        $metaKey = $key ?? 'meta_' . $property;
        $metaElement = $this->metaElementFactory->create([
            'attributes' => ['property' => $property, 'content' => $content]
        ]);
        return $this->addElement($metaElement, $metaKey);
    }

    /**
     * @inheritDoc
     */
    public function addCharset(string $charset = 'UTF-8'): self
    {
        $metaElement = $this->metaElementFactory->create(['attributes' => ['charset' => $charset]]);
        return $this->addElement($metaElement, 'charset');
    }

    /**
     * @inheritDoc
     */
    public function addLink(array $attributes, ?string $key = null): self
    {
        $linkElement = $this->linkElementFactory->create(['attributes' => $attributes]);
        return $this->addElement($linkElement, $key);
    }

    /**
     * @inheritDoc
     */
    public function addStylesheet(string $href, array $attributes = [], ?string $key = null): self
    {
        $cssKey = $key ?? 'css_' . md5($href);
        $linkElement = $this->linkElementFactory->create([
            'attributes' => ['rel' => 'stylesheet', 'href' => $href] + $attributes
        ]);
        return $this->addElement($linkElement, $cssKey);
    }

    /**
     * @inheritDoc
     */
    public function addScript(array $attributes, ?string $content = null, ?string $key = null): self
    {
        $scriptElement = $this->scriptElementFactory->create(['attributes' => $attributes, 'content' => $content]);
        $key = $key ?? 'script_' . md5(serialize($attributes) . $content);
        return $this->addElement($scriptElement, $key);
    }

    /**
     * @inheritDoc
     */
    public function addExternalScript(string $src, array $attributes = [], ?string $key = null): self
    {
        $scriptKey = $key ?? 'script_' . md5($src);
        $scriptElement = $this->scriptElementFactory->create(['attributes' => ['src' => $src] + $attributes]);
        return $this->addElement($scriptElement, $scriptKey);
    }

    /**
     * @inheritDoc
     */
    public function addInlineScript(string $content, array $attributes = [], ?string $key = null): self
    {
        $scriptKey = $key ?? 'script_inline_' . md5($content);
        $scriptElement = $this->scriptElementFactory->create(['attributes' => $attributes, 'content' => $content]);
        return $this->addElement($scriptElement, $scriptKey);
    }

    /**
     * @inheritDoc
     */
    public function addInlineStyle(string $content, array $attributes = [], ?string $key = null): self
    {
        $styleKey = $key ?? 'style_' . md5($content);
        $styleElement = $this->styleElementFactory->create(['attributes' => $attributes, 'content' => $content]);
        return $this->addElement($styleElement, $styleKey);
    }

    /**
     * @inheritDoc
     */
    public function render(): string
    {
        $output = '';

        foreach ($this->elements as $element) {
            $output .= $element->render() . PHP_EOL;
        }

        return $output;
    }

    /**
     * @inheritDoc
     */
    public function getRenderedElements(): array
    {
        return array_map(static function ($element) {
            return $element->render();
        }, $this->elements);
    }
}