<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\HeadElement\Fixture;

use Hryvinskyi\Base\Api\Html\AttributeRendererInterface;
use Hryvinskyi\Base\Test\Unit\Model\Html\Fixture\HtmlCoreServices;
use Magento\Framework\Escaper;
use Magento\Framework\Math\Random;
use Magento\Framework\Translate\Inline\ParserInterface;
use Magento\Framework\Translate\InlineInterface;
use Hryvinskyi\Base\View\Helper\SecureHtmlRender\HtmlRenderer;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\Framework\ZendEscaper;

/**
 * Builds the real renderers an element is constructed with, so rendered elements can be compared byte for byte
 */
class ElementServices
{
    /**
     * Builds Base's attribute renderer with the attribute order Base configures
     *
     * @return AttributeRendererInterface
     */
    public function attributeRenderer(): AttributeRendererInterface
    {
        return (new HtmlCoreServices())->attributeRenderer();
    }

    /**
     * Builds the framework's secure renderer without security processors, as on a store without CSP
     *
     * Its HTML renderer is Base's, which Base's DI configuration puts in place of the framework's on every install,
     * so a tag renders here as it does in production.
     *
     * @return SecureHtmlRenderer
     */
    public function secureHtmlRenderer(): SecureHtmlRenderer
    {
        return new SecureHtmlRenderer(
            new HtmlRenderer($this->escaper(), (new HtmlCoreServices())->tagRenderer()),
            new Random()
        );
    }

    /**
     * Builds the framework's escaper with the collaborators it otherwise fetches from the object manager
     *
     * @return Escaper
     */
    private function escaper(): Escaper
    {
        $escaper = new Escaper();
        (new \ReflectionProperty(Escaper::class, 'escaper'))->setValue($escaper, new ZendEscaper());
        (new \ReflectionProperty(Escaper::class, 'translateInline'))->setValue($escaper, $this->inlineTranslationOff());

        return $escaper;
    }

    /**
     * Builds an inline translator that is switched off, as on a storefront
     *
     * @return InlineInterface
     */
    private function inlineTranslationOff(): InlineInterface
    {
        return new class implements InlineInterface {
            /**
             * @inheritDoc
             */
            public function getAdditionalHtmlAttribute($tagName = null)
            {
                return null;
            }

            /**
             * @inheritDoc
             */
            public function isAllowed()
            {
                return false;
            }

            /**
             * Leave the response body as it is
             *
             * @param array<mixed>|string $body
             * @param bool $isJson
             * @return $this
             */
            public function processResponseBody(&$body, $isJson = false)
            {
                return $this;
            }

            /**
             * @inheritDoc
             */
            public function getParser(): ParserInterface
            {
                throw new \LogicException('Inline translation is switched off and has no parser.');
            }
        };
    }
}
