<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Integration\Fixture;

use Hryvinskyi\HeadTagManager\Api\HeadTagManagerInterface;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Context;

/**
 * A block that adds stylesheets to the head while it renders, and renders its children after it
 *
 * Its data names the stylesheet `_toHtml()` adds (`stylesheet_href`, `stylesheet_key`, `stylesheet_attributes`), the
 * one `_afterToHtml()` adds on a cache hit and a render alike (`after_stylesheet_href`, `after_stylesheet_key`), and
 * whether `_toHtml()` throws once it has added its stylesheet (`throw_on_render`); `cache_lifetime` and `cache_key`
 * make it a cached block. It counts how often its HTML was actually produced, so a test can tell a cache hit from a
 * render.
 */
class HeadElementBlock extends AbstractBlock
{
    /**
     * @var int
     */
    private int $renderCount = 0;

    /**
     * @param Context $context
     * @param HeadTagManagerInterface $headTagManager receives the stylesheets
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly HeadTagManagerInterface $headTagManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * How often the block produced its HTML instead of taking it from the cache
     *
     * @return int
     */
    public function getRenderCount(): int
    {
        return $this->renderCount;
    }

    /**
     * Adds the stylesheet the block's data names, then renders the children
     *
     * @return string
     * @throws \RuntimeException when the block's data asks it to fail
     */
    protected function _toHtml(): string
    {
        $this->renderCount++;

        $href = $this->getData('stylesheet_href');
        $key = $this->getData('stylesheet_key');
        if (is_string($href) && is_string($key)) {
            $this->headTagManager->addStylesheet($href, $this->stylesheetAttributes(), $key);
        }

        if ($this->getData('throw_on_render')) {
            throw new \RuntimeException('The block failed to render.');
        }

        return '<div>' . $this->getNameInLayout() . $this->getChildHtml() . '</div>';
    }

    /**
     * Adds the stylesheet the block's data names for the step after its HTML is produced or loaded
     *
     * @param string $html
     * @return string
     */
    protected function _afterToHtml($html)
    {
        $href = $this->getData('after_stylesheet_href');
        $key = $this->getData('after_stylesheet_key');
        if (is_string($href) && is_string($key)) {
            $this->headTagManager->addStylesheet($href, [], $key);
        }

        return $html;
    }

    /**
     * The extra stylesheet attributes the block's data names, keyed by attribute name
     *
     * @return array<string, string|array<mixed>>
     */
    private function stylesheetAttributes(): array
    {
        $attributes = [];
        $data = $this->getData('stylesheet_attributes');
        foreach (is_array($data) ? $data : [] as $name => $value) {
            if (is_string($name) && (is_string($value) || is_array($value))) {
                $attributes[$name] = $value;
            }
        }

        return $attributes;
    }
}
