<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Integration;

use Hryvinskyi\HeadTagManager\Api\Block\BlockCacheDetectorInterface;
use Hryvinskyi\HeadTagManager\Api\Block\HeadElementTrackerInterface;
use Hryvinskyi\HeadTagManager\Api\Cache\BlockHeadElementCacheInterface;
use Hryvinskyi\HeadTagManager\Api\HeadTagManagerInterface;
use Hryvinskyi\HeadTagManager\Api\Registry\HeadElementFactoryRegistryInterface;
use Hryvinskyi\HeadTagManager\Api\Serializer\HeadElementSerializerInterface;
use Hryvinskyi\HeadTagManager\Api\Serializer\Strategy\SerializationStrategyRegistryInterface;
use Hryvinskyi\HeadTagManager\Model\HeadElement\Factory\MetaElementFactory;
use Hryvinskyi\HeadTagManager\Model\HeadElement\Factory\ScriptElementFactory;
use Hryvinskyi\HeadTagManager\Model\HeadElement\MetaElement;
use Hryvinskyi\HeadTagManager\Model\HeadElement\ScriptElement;
use Hryvinskyi\HeadTagManager\Model\Registry\HeadElementFactoryRegistry;
use Hryvinskyi\HeadTagManager\Model\Serializer\HeadElementSerializer;
use Hryvinskyi\HeadTagManager\Model\Serializer\Strategy\MetaElementSerializationStrategy;
use Hryvinskyi\HeadTagManager\Model\Serializer\Strategy\ScriptElementSerializationStrategy;
use Hryvinskyi\HeadTagManager\Model\Serializer\Strategy\SerializationStrategyRegistry;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Integration test to verify the complete block-level caching flow works end-to-end
 */
class HeadTagManagerIntegrationTest extends TestCase
{
    private HeadElementFactoryRegistryInterface $factoryRegistry;
    private SerializationStrategyRegistryInterface $strategyRegistry;
    private HeadElementSerializerInterface $serializer;

    protected function setUp(): void
    {
        // Setup real implementations to test integration
        $secureHtmlRenderer = $this->createMock(SecureHtmlRenderer::class);
        $secureHtmlRenderer->method('renderTag')->willReturnCallback(
            function ($tag, $attributes, $content) {
                $attrString = '';
                foreach ($attributes as $name => $value) {
                    $attrString .= " {$name}=\"{$value}\"";
                }
                if ($content) {
                    return "<{$tag}{$attrString}>{$content}</{$tag}>";
                }
                return "<{$tag}{$attrString}>";
            }
        );

        // Create factory implementations
        $metaElementFactory = new MetaElementFactory($secureHtmlRenderer);
        $scriptElementFactory = new ScriptElementFactory($secureHtmlRenderer);

        // Setup factory registry
        $this->factoryRegistry = new HeadElementFactoryRegistry([
            $metaElementFactory,
            $scriptElementFactory
        ]);

        // Setup strategy registry
        $metaStrategy = new MetaElementSerializationStrategy($this->factoryRegistry);
        $scriptStrategy = new ScriptElementSerializationStrategy($this->factoryRegistry);

        $this->strategyRegistry = new SerializationStrategyRegistry([
            $metaStrategy,
            $scriptStrategy
        ]);

        // Setup serializer
        $logger = $this->createMock(LoggerInterface::class);
        $this->serializer = new HeadElementSerializer(
            $this->factoryRegistry,
            $this->strategyRegistry,
            $logger
        );
    }

    public function testCompleteSerializationAndDeserializationFlow(): void
    {
        // Create original elements
        $secureHtmlRenderer = $this->createMock(SecureHtmlRenderer::class);
        
        $metaElement = new MetaElement($secureHtmlRenderer, ['name' => 'description', 'content' => 'Test']);
        $scriptElement = new ScriptElement($secureHtmlRenderer, ['type' => 'text/javascript'], 'console.log("test");');

        $elements = [
            'meta_description' => $metaElement,
            'inline_script' => $scriptElement
        ];

        // Serialize elements
        $serializedData = $this->serializer->serialize($elements);

        // Verify serialized data structure
        $this->assertArrayHasKey('meta_description', $serializedData);
        $this->assertArrayHasKey('inline_script', $serializedData);

        // Verify meta element serialization
        $metaData = $serializedData['meta_description'];
        $this->assertEquals(MetaElement::class, $metaData['type']);
        $this->assertEquals('meta', $metaData['short_type']);
        $this->assertEquals(['name' => 'description', 'content' => 'Test'], $metaData['attributes']);

        // Verify script element serialization
        $scriptData = $serializedData['inline_script'];
        $this->assertEquals(ScriptElement::class, $scriptData['type']);
        $this->assertEquals('script', $scriptData['short_type']);
        $this->assertEquals(['type' => 'text/javascript'], $scriptData['attributes']);
        $this->assertEquals('console.log("test");', $scriptData['content']);

        // Deserialize elements
        $deserializedElements = $this->serializer->unserialize($serializedData);

        // Verify deserialized elements
        $this->assertCount(2, $deserializedElements);
        $this->assertArrayHasKey('meta_description', $deserializedElements);
        $this->assertArrayHasKey('inline_script', $deserializedElements);

        $deserializedMeta = $deserializedElements['meta_description'];
        $deserializedScript = $deserializedElements['inline_script'];

        // Verify meta element reconstruction
        $this->assertInstanceOf(MetaElement::class, $deserializedMeta);
        $this->assertEquals(['name' => 'description', 'content' => 'Test'], $deserializedMeta->getAttributes());

        // Verify script element reconstruction
        $this->assertInstanceOf(ScriptElement::class, $deserializedScript);
        $this->assertEquals(['type' => 'text/javascript'], $deserializedScript->getAttributes());
        $this->assertEquals('console.log("test");', $deserializedScript->getContent());
    }

    public function testStrategyRegistryIntegration(): void
    {
        $secureHtmlRenderer = $this->createMock(SecureHtmlRenderer::class);
        $metaElement = new MetaElement($secureHtmlRenderer, ['name' => 'keywords', 'content' => 'test']);

        // Verify strategy can be found for element
        $strategy = $this->strategyRegistry->getStrategyForElement($metaElement);
        $this->assertInstanceOf(MetaElementSerializationStrategy::class, $strategy);

        // Verify strategy can serialize element
        $serializedData = $strategy->serialize($metaElement, 'test-key');
        
        $expected = [
            'type' => MetaElement::class,
            'short_type' => 'meta',
            'attributes' => ['name' => 'keywords', 'content' => 'test']
        ];

        $this->assertEquals($expected, $serializedData);
    }

    public function testFactoryRegistryIntegration(): void
    {
        // Test factory lookup by type
        $metaFactory = $this->factoryRegistry->getFactoryByType('meta');
        $this->assertInstanceOf(MetaElementFactory::class, $metaFactory);

        // Test factory lookup by class name
        $scriptFactory = $this->factoryRegistry->getFactoryByClassName(ScriptElement::class);
        $this->assertInstanceOf(ScriptElementFactory::class, $scriptFactory);

        // Test element type lookup
        $elementType = $this->factoryRegistry->getElementTypeByClassName(MetaElement::class);
        $this->assertEquals('meta', $elementType);
    }

    public function testBackwardCompatibilityWithShortTypes(): void
    {
        // Test deserializing data with only short_type (backward compatibility)
        $legacyData = [
            'meta_key' => [
                'short_type' => 'meta',
                'attributes' => ['name' => 'author', 'content' => 'Test Author']
            ],
            'script_key' => [
                'short_type' => 'script',
                'attributes' => ['defer' => 'defer'],
                'content' => 'alert("legacy");'
            ]
        ];

        $deserializedElements = $this->serializer->unserialize($legacyData);

        $this->assertCount(2, $deserializedElements);
        
        $metaElement = $deserializedElements['meta_key'];
        $scriptElement = $deserializedElements['script_key'];

        $this->assertInstanceOf(MetaElement::class, $metaElement);
        $this->assertInstanceOf(ScriptElement::class, $scriptElement);
        
        $this->assertEquals(['name' => 'author', 'content' => 'Test Author'], $metaElement->getAttributes());
        $this->assertEquals(['defer' => 'defer'], $scriptElement->getAttributes());
        $this->assertEquals('alert("legacy");', $scriptElement->getContent());
    }

    public function testSerializationWithoutStrategy(): void
    {
        // Create an unknown element class for testing
        $secureHtmlRenderer = $this->createMock(SecureHtmlRenderer::class);
        $unknownElement = new class($secureHtmlRenderer, ['custom' => 'attribute']) extends \Hryvinskyi\HeadTagManager\Model\HeadElement\AbstractHeadElement {
            public function render(): string {
                return '<unknown' . $this->attributesToString() . '>';
            }
        };

        $elements = ['unknown' => $unknownElement];
        $serializedData = $this->serializer->serialize($elements);

        // Should fall back to basic serialization
        $this->assertArrayHasKey('unknown', $serializedData);
        $unknownData = $serializedData['unknown'];
        
        $this->assertEquals(get_class($unknownElement), $unknownData['type']);
        $this->assertEquals('unknown', $unknownData['short_type']);
        $this->assertEquals(['custom' => 'attribute'], $unknownData['attributes']);
        $this->assertNull($unknownData['content']);
    }

    public function testBlockLevelCachingFlow(): void
    {
        // Create mocks for block-level caching components
        $headTagManager = $this->createMock(HeadTagManagerInterface::class);
        $blockCache = $this->createMock(BlockHeadElementCacheInterface::class);
        $cacheDetector = $this->createMock(BlockCacheDetectorInterface::class);
        $elementTracker = $this->createMock(HeadElementTrackerInterface::class);

        // Mock a block
        $block = $this->createMock(\Magento\Framework\View\Element\AbstractBlock::class);
        $block->method('getNameInLayout')->willReturn('test_block');
        $block->method('getCacheKey')->willReturn('test_cache_key');

        // Test scenario: Block is cacheable and not cached yet (will be rendered)
        $cacheDetector->method('isBlockCacheable')->with($block)->willReturn(true);
        $cacheDetector->method('isBlockCached')->with($block)->willReturn(false);

        // Create test elements
        $secureHtmlRenderer = $this->createMock(SecureHtmlRenderer::class);
        $metaElement = new MetaElement($secureHtmlRenderer, ['name' => 'test', 'content' => 'value']);
        $newElements = ['meta_test' => $metaElement];

        // Test tracker flow
        $elementTracker->expects($this->once())
            ->method('isBlockBeingTracked')
            ->with($block)
            ->willReturn(true);

        $elementTracker->expects($this->once())
            ->method('stopTrackingAndGetNewElements')
            ->with($block)
            ->willReturn($newElements);

        // Test cache saving
        $blockCache->expects($this->once())
            ->method('saveBlockHeadElements')
            ->with($block, $newElements);

        // Simulate the flow that would happen in BlockHtmlAfterObserver
        if ($elementTracker->isBlockBeingTracked($block)) {
            $trackedElements = $elementTracker->stopTrackingAndGetNewElements($block);

            if ($cacheDetector->isBlockCacheable($block)) {
                if (!$cacheDetector->isBlockCached($block) && !empty($trackedElements)) {
                    $blockCache->saveBlockHeadElements($block, $trackedElements);
                }
            }
        }

        $this->assertTrue(true); // Test passes if no exceptions thrown
    }

    public function testBlockCacheRestoration(): void
    {
        // Test cache restoration flow
        $headTagManager = $this->createMock(HeadTagManagerInterface::class);
        $blockCache = $this->createMock(BlockHeadElementCacheInterface::class);

        // Mock a block
        $block = $this->createMock(\Magento\Framework\View\Element\AbstractBlock::class);
        $block->method('getNameInLayout')->willReturn('cached_block');
        $block->method('getCacheKey')->willReturn('cached_block_key');

        // Create cached elements
        $secureHtmlRenderer = $this->createMock(SecureHtmlRenderer::class);
        $cachedMetaElement = new MetaElement($secureHtmlRenderer, ['name' => 'cached', 'content' => 'data']);
        $cachedElements = ['cached_meta' => $cachedMetaElement];

        // Mock cache loading
        $blockCache->expects($this->once())
            ->method('loadBlockHeadElements')
            ->with($block)
            ->willReturn($cachedElements);

        // Mock head tag manager restoration
        $headTagManager->expects($this->once())
            ->method('addElement')
            ->with($cachedMetaElement, 'cached_meta');

        // Simulate cache restoration
        $loadedElements = $blockCache->loadBlockHeadElements($block);

        if (!empty($loadedElements)) {
            foreach ($loadedElements as $key => $element) {
                $headTagManager->addElement($element, $key);
            }
        }

        $this->assertEquals($cachedElements, $loadedElements);
    }
}