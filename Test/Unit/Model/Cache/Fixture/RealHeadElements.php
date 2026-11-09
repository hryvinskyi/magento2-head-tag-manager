<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\Cache\Fixture;

use Hryvinskyi\HeadTagManager\Model\Cache\CachedBlockHtmlCodec;
use Hryvinskyi\HeadTagManager\Model\HeadElement\Factory\LinkElementFactory;
use Hryvinskyi\HeadTagManager\Model\HeadElement\Factory\MetaElementFactory;
use Hryvinskyi\HeadTagManager\Model\HeadElement\Factory\ScriptElementFactory;
use Hryvinskyi\HeadTagManager\Model\HeadElement\Factory\StyleElementFactory;
use Hryvinskyi\HeadTagManager\Model\HeadElement\LinkElement;
use Hryvinskyi\HeadTagManager\Model\HeadElement\LinkElementFactory as LinkElementObjectFactory;
use Hryvinskyi\HeadTagManager\Model\HeadElement\MetaElement;
use Hryvinskyi\HeadTagManager\Model\HeadElement\MetaElementFactory as MetaElementObjectFactory;
use Hryvinskyi\HeadTagManager\Model\HeadElement\ScriptElement;
use Hryvinskyi\HeadTagManager\Model\HeadElement\ScriptElementFactory as ScriptElementObjectFactory;
use Hryvinskyi\HeadTagManager\Model\HeadElement\StyleElement;
use Hryvinskyi\HeadTagManager\Model\HeadElement\StyleElementFactory as StyleElementObjectFactory;
use Hryvinskyi\HeadTagManager\Model\HeadTagManager;
use Hryvinskyi\HeadTagManager\Model\Registry\HeadElementFactoryRegistry;
use Hryvinskyi\HeadTagManager\Model\Serializer\HeadElementSerializer;
use Hryvinskyi\HeadTagManager\Model\Serializer\StorableAttributes;
use Hryvinskyi\HeadTagManager\Model\Serializer\Strategy\LinkElementSerializationStrategy;
use Hryvinskyi\HeadTagManager\Model\Serializer\Strategy\MetaElementSerializationStrategy;
use Hryvinskyi\HeadTagManager\Model\Serializer\Strategy\ScriptElementSerializationStrategy;
use Hryvinskyi\HeadTagManager\Model\Serializer\Strategy\SerializationStrategyRegistry;
use Hryvinskyi\HeadTagManager\Model\Serializer\Strategy\StyleElementSerializationStrategy;
use Hryvinskyi\HeadTagManager\Test\Unit\Model\HeadElement\Fixture\ElementServices;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Encryption\KeyValidator;
use Magento\Framework\Math\Random;
use Magento\Framework\Serialize\Serializer\Base64Json;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * The module's real element graph, as its DI configuration assembles it: the four element factories plus a custom
 * one, the four serialization strategies, the element serializer, the block HTML codec and a head tag manager over
 * the same registry
 *
 * Only the factories Magento generates and the deployment configuration that holds the crypt key are doubled; each
 * factory creates a real element with the real renderers.
 *
 * @phpstan-require-extends TestCase
 */
trait RealHeadElements
{
    /**
     * @var HeadElementFactoryRegistry|null
     */
    private ?HeadElementFactoryRegistry $elementRegistry = null;

    /**
     * The registry of every element factory, the custom element's included
     *
     * @return HeadElementFactoryRegistry
     */
    private function elementRegistry(): HeadElementFactoryRegistry
    {
        if ($this->elementRegistry !== null) {
            return $this->elementRegistry;
        }

        $services = new ElementServices();
        $secure = $services->secureHtmlRenderer();
        $attributeRenderer = $services->attributeRenderer();

        $links = $this->createStub(LinkElementObjectFactory::class);
        $links->method('create')->willReturnCallback(
            static fn (): LinkElement => new LinkElement($attributeRenderer, $secure)
        );
        $metas = $this->createStub(MetaElementObjectFactory::class);
        $metas->method('create')->willReturnCallback(
            static fn (): MetaElement => new MetaElement($attributeRenderer)
        );
        $scripts = $this->createStub(ScriptElementObjectFactory::class);
        $scripts->method('create')->willReturnCallback(
            static fn (): ScriptElement => new ScriptElement($attributeRenderer, $secure)
        );
        $styles = $this->createStub(StyleElementObjectFactory::class);
        $styles->method('create')->willReturnCallback(
            static fn (): StyleElement => new StyleElement($secure)
        );

        $this->elementRegistry = new HeadElementFactoryRegistry([
            'meta' => new MetaElementFactory($metas),
            'link' => new LinkElementFactory($links),
            'script' => new ScriptElementFactory($scripts),
            'style' => new StyleElementFactory($styles),
            'custom' => new CustomHeadElementFactory(),
        ]);

        return $this->elementRegistry;
    }

    /**
     * The element serializer with the module's four strategies and no strategy for the custom element
     *
     * @param LoggerInterface|null $logger
     * @return HeadElementSerializer
     */
    private function elementSerializer(?LoggerInterface $logger = null): HeadElementSerializer
    {
        $registry = $this->elementRegistry();
        $storable = new StorableAttributes();

        return new HeadElementSerializer(
            $registry,
            new SerializationStrategyRegistry([
                'meta' => new MetaElementSerializationStrategy($registry, $storable),
                'link' => new LinkElementSerializationStrategy($registry, $storable),
                'script' => new ScriptElementSerializationStrategy($registry, $storable),
                'style' => new StyleElementSerializationStrategy($registry, $storable),
            ]),
            $logger ?? new NullLogger(),
            $storable
        );
    }

    /**
     * The block HTML codec over the real element serializer, the framework's JSON serializers and its encryptor
     *
     * @param LoggerInterface|null $logger receives the warnings of the codec and of its element serializer
     * @param string $cryptKey the installation's key the codec signs its comment with
     * @return CachedBlockHtmlCodec
     */
    private function blockHtmlCodec(
        ?LoggerInterface $logger = null,
        string $cryptKey = 'a3f1c9e07b2d4e8f9a6c5b4d3e2f1a0b'
    ): CachedBlockHtmlCodec {
        $logger ??= new NullLogger();

        return new CachedBlockHtmlCodec(
            $this->elementSerializer($logger),
            new Base64Json(),
            $this->encryptor($cryptKey),
            $logger
        );
    }

    /**
     * The framework's encryptor over a deployment whose crypt key is the given one
     *
     * @param string $cryptKey
     * @return EncryptorInterface
     */
    private function encryptor(string $cryptKey = 'a3f1c9e07b2d4e8f9a6c5b4d3e2f1a0b'): EncryptorInterface
    {
        $deploymentConfig = $this->createStub(DeploymentConfig::class);
        $deploymentConfig->method('get')->willReturnCallback(
            static fn (?string $path = null): ?string => $path === 'crypt/key' ? $cryptKey : null
        );

        return new Encryptor(new Random(), $deploymentConfig, new KeyValidator());
    }

    /**
     * A head tag manager over the same factories
     *
     * @return HeadTagManager
     */
    private function newHeadTagManager(): HeadTagManager
    {
        return new HeadTagManager($this->elementRegistry(), new Json(), new NullLogger());
    }
}
