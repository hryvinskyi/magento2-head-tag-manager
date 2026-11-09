<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\Cache;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Model\Cache\CachedBlockHtmlCodec;
use Hryvinskyi\HeadTagManager\Model\HeadElement\LinkElement;
use Hryvinskyi\HeadTagManager\Model\HeadElement\MetaElement;
use Hryvinskyi\HeadTagManager\Model\HeadElement\ScriptElement;
use Hryvinskyi\HeadTagManager\Test\Unit\Model\Cache\Fixture\CustomHeadElement;
use Hryvinskyi\HeadTagManager\Test\Unit\Model\Cache\Fixture\RealHeadElements;
use Magento\Framework\Phrase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The codec over the real element serializer, its strategies and factories, and the framework's JSON serializers
 */
class CachedBlockHtmlCodecTest extends TestCase
{
    use RealHeadElements;

    private const MARKER = '<!--hryvinskyi-head-elements:1:';

    /**
     * @var LoggerInterface&MockObject
     */
    private MockObject $logger;

    /**
     * @var CachedBlockHtmlCodec
     */
    private CachedBlockHtmlCodec $codec;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->codec = $this->blockHtmlCodec($this->logger);
    }

    /**
     * A comment carrying the payload, signed with the installation's key or the given other one
     *
     * @param string $payload
     * @param string|null $cryptKey
     * @return string
     */
    private function signedMarker(string $payload, ?string $cryptKey = null): string
    {
        $encryptor = $cryptKey === null ? $this->encryptor() : $this->encryptor($cryptKey);

        return self::MARKER . substr($encryptor->hash($payload), 0, 32) . ':' . $payload . '-->';
    }

    /**
     * The JSON a block that added one stylesheet would store
     *
     * @return string
     */
    private function stylesheetJson(): string
    {
        $manager = $this->newHeadTagManager();
        $manager->addStylesheet('/forged.css', [], 'forged');

        return (string)json_encode($this->elementSerializer()->serialize($manager->getAllElements()));
    }

    /**
     * The base64 of the JSON a block that added one stylesheet would store
     *
     * @return string
     */
    private function stylesheetPayload(): string
    {
        return base64_encode($this->stylesheetJson());
    }

    public function testElementsComeBackWithTheirKeysTypesAttributesContentAndSortOrder(): void
    {
        $manager = $this->newHeadTagManager();
        $manager->addStylesheet('/a.css', ['data' => ['variant' => 'hero'], 'media' => 'print'], 'sheet');
        $manager->addMeta(['name' => 'title', 'content' => new Phrase('Hello %1', ['world'])], 'title');
        $manager->createElement('script', ['content' => 'run();', 'sort_order' => 7], 'boot');
        $manager->addCharset();
        $elements = $manager->getAllElements();

        $decoded = $this->codec->decode($this->codec->encode('<div>block</div>', $elements));

        self::assertNotNull($decoded);
        self::assertSame('<div>block</div>', $decoded['html']);
        self::assertSame(['sheet', 'title', 'boot', 'charset'], array_keys($decoded['elements']));
        self::assertInstanceOf(LinkElement::class, $decoded['elements']['sheet']);
        self::assertInstanceOf(MetaElement::class, $decoded['elements']['title']);
        self::assertInstanceOf(ScriptElement::class, $decoded['elements']['boot']);
        self::assertSame(
            ['rel' => 'stylesheet', 'href' => '/a.css', 'data' => ['variant' => 'hero'], 'media' => 'print'],
            $decoded['elements']['sheet']->getAttributes()
        );
        self::assertSame(
            ['name' => 'title', 'content' => 'Hello world'],
            $decoded['elements']['title']->getAttributes()
        );
        self::assertSame('run();', $decoded['elements']['boot']->getContent());
        self::assertSame(7, $decoded['elements']['boot']->getSortOrder());
        foreach ($elements as $key => $element) {
            self::assertSame($element->render(), $decoded['elements'][$key]->render(), $key);
            self::assertSame($element->getSortOrder(), $decoded['elements'][$key]->getSortOrder(), $key);
        }
    }

    public function testACustomElementWithoutAStrategyKeepsTheTextOfAStringableAttribute(): void
    {
        $label = new class implements \Stringable {
            /**
             * @inheritDoc
             */
            public function __toString(): string
            {
                return 'Sale now';
            }
        };
        $element = $this->elementRegistry()->getFactoryByType('custom')?->create(['attributes' => ['label' => $label]]);
        self::assertInstanceOf(CustomHeadElement::class, $element);

        $decoded = $this->codec->decode($this->codec->encode('', ['custom' => $element]));

        self::assertNotNull($decoded);
        self::assertInstanceOf(CustomHeadElement::class, $decoded['elements']['custom']);
        self::assertSame(['label' => 'Sale now'], $decoded['elements']['custom']->getAttributes());
        self::assertSame('<custom label="Sale now">', $decoded['elements']['custom']->render());
    }

    public function testNoElementsStillWriteTheMarkerAndComeBackEmpty(): void
    {
        $entry = $this->codec->encode('<p>plain</p>', []);

        self::assertMatchesRegularExpression(
            '~^<p>plain</p><!--hryvinskyi-head-elements:1:[0-9a-f]{32}:[A-Za-z0-9+/]+={0,2}-->$~',
            $entry
        );
        self::assertSame(['html' => '<p>plain</p>', 'elements' => []], $this->codec->decode($entry));
    }

    public function testThePayloadKeepsSlashesAndTextAsTheyAreAndNamesNoClassAFactoryLookupDoesNotNeed(): void
    {
        $manager = $this->newHeadTagManager();
        $manager->addStylesheet('/css/a.css', [], 'sheet');
        $manager->addMetaName('description', 'Café crème', 'description');
        $custom = $this->elementRegistry()->getFactoryByType('custom')?->create(['attributes' => ['label' => 'x']]);
        self::assertInstanceOf(CustomHeadElement::class, $custom);

        $entry = $this->codec->encode('<div>a</div>', $manager->getAllElements() + ['custom' => $custom]);
        $json = $this->payloadJson($entry);

        self::assertStringContainsString('"/css/a.css"', $json);
        self::assertStringContainsString('"Café crème"', $json);
        self::assertStringNotContainsString('HeadElement', $json);
        $decoded = $this->codec->decode($entry);
        self::assertNotNull($decoded);
        self::assertInstanceOf(LinkElement::class, $decoded['elements']['sheet']);
        self::assertInstanceOf(MetaElement::class, $decoded['elements']['description']);
        self::assertInstanceOf(CustomHeadElement::class, $decoded['elements']['custom']);
    }

    /**
     * The JSON an entry's comment carries
     *
     * @param string $entry
     * @return string
     */
    private function payloadJson(string $entry): string
    {
        $payload = preg_match('~:([A-Za-z0-9+/]*={0,2})-->$~', $entry, $match) === 1 ? $match[1] : '';
        self::assertNotSame('', $payload);

        return (string)base64_decode($payload, true);
    }

    public function testCommentsInTheHtmlAndInAScriptsContentSurvive(): void
    {
        $html = '<!-- a comment --><div>--></div><!--';
        $manager = $this->newHeadTagManager();
        $manager->addInlineScript('if (a --> 0) { b(); } // -->', [], 'loop');

        $decoded = $this->codec->decode($this->codec->encode($html, $manager->getAllElements()));

        self::assertNotNull($decoded);
        self::assertSame($html, $decoded['html']);
        $script = $decoded['elements']['loop'];
        self::assertInstanceOf(ScriptElement::class, $script);
        self::assertSame('if (a --> 0) { b(); } // -->', $script->getContent());
    }

    public function testAnEntryWithoutAMarkerIsNotRead(): void
    {
        $this->logger->expects(self::never())->method(self::anything());

        self::assertNull($this->codec->decode('<div>written before the marker existed</div>'));
    }

    public function testAnEmptyEntryIsNotRead(): void
    {
        $this->logger->expects(self::never())->method(self::anything());

        self::assertNull($this->codec->decode(''));
    }

    public function testAMarkerThatIsNotAtTheVeryEndIsNotRead(): void
    {
        $this->logger->expects(self::never())->method(self::anything());

        self::assertNull($this->codec->decode($this->codec->encode('<div>a</div>', []) . '<div>after</div>'));
        self::assertNull($this->codec->decode($this->codec->encode('<div>a</div>', []) . "\n"));
    }

    public function testAMarkerOfAnotherFormatVersionIsNotRead(): void
    {
        $this->logger->expects(self::never())->method(self::anything());

        $entry = '<div>a</div><!--hryvinskyi-head-elements:2:' . base64_encode('[]') . '-->';

        self::assertNull($this->codec->decode($entry));
    }

    public function testAMarkerOnEmptyHtmlIsAHitWithEmptyHtml(): void
    {
        self::assertSame(['html' => '', 'elements' => []], $this->codec->decode($this->codec->encode('', [])));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function signedPayloadsThatAreNotBase64Json(): array
    {
        return [
            'not base64 at all' => ['!!not base64 at all!!'],
            'raw JSON' => ['{"forged":{"type":"link","attributes":{"href":"/x.css"}}}'],
            'base64 that is no JSON' => [base64_encode('not json')],
        ];
    }

    #[DataProvider('signedPayloadsThatAreNotBase64Json')]
    public function testASignedPayloadThatIsNotBase64JsonIsNotReadAndIsLoggedOnce(string $payload): void
    {
        $this->logger->expects(self::once())->method('warning');

        self::assertNull($this->codec->decode('<div>a</div>' . $this->signedMarker($payload)));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function payloadsThatAreNoElementMap(): array
    {
        return [
            'a number' => ['5'],
            'null' => ['null'],
            'a string' => ['"text"'],
            'a boolean' => ['true'],
        ];
    }

    #[DataProvider('payloadsThatAreNoElementMap')]
    public function testAPayloadThatIsNoElementMapIsNotReadAndIsLoggedOnce(string $json): void
    {
        $this->logger->expects(self::once())->method('warning');

        self::assertNull($this->codec->decode('<div>a</div>' . $this->signedMarker(base64_encode($json))));
    }

    public function testAMarkerWithoutASignatureIsNotRead(): void
    {
        $this->logger->expects(self::never())->method(self::anything());

        self::assertNull($this->codec->decode('<div>a</div>' . self::MARKER . $this->stylesheetPayload() . '-->'));
    }

    public function testAMarkerCarryingRawJsonIsNotRead(): void
    {
        $this->logger->expects(self::never())->method(self::anything());
        self::assertNull($this->codec->decode('<div>a</div>' . self::MARKER . $this->stylesheetJson() . '-->'));
    }

    public function testAMarkerWithAWrongSignatureIsNotRead(): void
    {
        $this->logger->expects(self::never())->method(self::anything());
        $entry = '<div>a</div>' . self::MARKER . str_repeat('0', 64) . ':' . $this->stylesheetPayload() . '-->';

        self::assertNull($this->codec->decode($entry));
    }

    public function testAMarkerSignedWithAnotherKeyIsNotRead(): void
    {
        $this->logger->expects(self::never())->method(self::anything());
        $entry = '<div>a</div>' . $this->signedMarker($this->stylesheetPayload(), 'another installation key 0123456');

        self::assertNull($this->codec->decode($entry));
    }

    public function testAnEntryWrittenUnderAnotherKeyIsNotRead(): void
    {
        $manager = $this->newHeadTagManager();
        $manager->addStylesheet('/a.css', [], 'sheet');
        $entry = $this->blockHtmlCodec(null, 'another installation key 0123456')
            ->encode('<div>a</div>', $manager->getAllElements());

        self::assertNull($this->codec->decode($entry));
        self::assertNotNull($this->blockHtmlCodec(null, 'another installation key 0123456')->decode($entry));
    }

    public function testAnElementThatCannotBeRecreatedIsLeftOutAndTheRestComeBack(): void
    {
        $manager = $this->newHeadTagManager();
        $manager->addStylesheet('/kept.css', [], 'kept');
        $payload = $this->elementSerializer()->serialize($manager->getAllElements());
        $payload['gone'] = ['type' => 'Vendor\\Gone\\Element', 'short_type' => 'gone', 'attributes' => []];

        $entry = '<div>a</div>' . $this->signedMarker(base64_encode((string)json_encode($payload)));

        $decoded = $this->codec->decode($entry);

        self::assertNotNull($decoded);
        self::assertSame(['kept'], array_keys($decoded['elements']));
    }

    public function testAnElementTheSerializerSkipsIsLeftOutOfAMarkedEntryWithOneWarning(): void
    {
        $manager = $this->newHeadTagManager();
        $manager->addStylesheet('/a.css', [], 'sheet');
        $elements = $manager->getAllElements();
        $elements['broken'] = $this->unserializableElement();
        $this->logger->expects(self::once())->method('warning')
            ->with(self::anything(), self::callback(static fn (array $context): bool => $context['key'] === 'broken'));

        $decoded = $this->codec->decode($this->codec->encode('<div>a</div>', $elements));

        self::assertNotNull($decoded);
        self::assertSame('<div>a</div>', $decoded['html']);
        self::assertSame(['sheet'], array_keys($decoded['elements']));
    }

    public function testAnElementWhoseDataIsNotUtf8IsLeftOutOfAMarkedEntryWithOneWarning(): void
    {
        $manager = $this->newHeadTagManager();
        $manager->addMetaName('description', "caf\xE9", 'description');
        $manager->addStylesheet('/a.css', [], 'sheet');
        $this->logger->expects(self::once())->method('warning')
            ->with(self::anything(), self::callback(
                static fn (array $context): bool => $context['key'] === 'description'
            ));

        $decoded = $this->codec->decode($this->codec->encode('<div>a</div>', $manager->getAllElements()));

        self::assertNotNull($decoded);
        self::assertSame(['sheet'], array_keys($decoded['elements']));
    }

    public function testWhenNoElementCanBeEncodedTheEntryIsStillMarked(): void
    {
        $manager = $this->newHeadTagManager();
        $manager->addMetaName('description', "caf\xE9", 'description');

        $decoded = $this->codec->decode($this->codec->encode('<div>a</div>', $manager->getAllElements()));

        self::assertSame(['html' => '<div>a</div>', 'elements' => []], $decoded);
    }

    /**
     * An element whose attributes cannot be read, which the element serializer logs and skips
     *
     * @return HeadElementInterface
     */
    private function unserializableElement(): HeadElementInterface
    {
        return new class implements HeadElementInterface {
            /**
             * @inheritDoc
             */
            public function render(): string
            {
                return '<broken>';
            }

            /**
             * @inheritDoc
             */
            public function getAttributes(): array
            {
                throw new \RuntimeException('The attributes cannot be read.');
            }

            /**
             * @inheritDoc
             */
            public function getSortOrder(): int
            {
                return 1;
            }
        };
    }
}
