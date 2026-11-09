<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Unit\Model\Cache;

use Hryvinskyi\HeadTagManager\Api\Cache\CachedBlockHtmlCodecInterface;
use Hryvinskyi\HeadTagManager\Api\HeadTagManagerInterface;
use Hryvinskyi\HeadTagManager\Model\Cache\BlockHtmlCacheLoader;
use Hryvinskyi\HeadTagManager\Model\Cache\CachedBlockHtmlCodec;
use Hryvinskyi\HeadTagManager\Model\HeadElement\LinkElement;
use Hryvinskyi\HeadTagManager\Model\HeadTagManager;
use Hryvinskyi\HeadTagManager\Test\Unit\Model\Cache\Fixture\RealHeadElements;
use Magento\Framework\Phrase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * The loader service over a real head tag manager and the real codec, with recording actions in place of a block's
 */
class BlockHtmlCacheLoaderTest extends TestCase
{
    use RealHeadElements;

    /**
     * @var HeadTagManager
     */
    private HeadTagManager $manager;

    /**
     * @var CachedBlockHtmlCodec
     */
    private CachedBlockHtmlCodec $codec;

    /**
     * @var list<mixed> what the framework's save action was given, one entry per call
     */
    private array $saved = [];

    protected function setUp(): void
    {
        $this->manager = $this->newHeadTagManager();
        $this->codec = $this->blockHtmlCodec();
    }

    /**
     * The service under test
     *
     * @param LoggerInterface|null $logger
     * @return BlockHtmlCacheLoader
     */
    private function subject(?LoggerInterface $logger = null): BlockHtmlCacheLoader
    {
        return new BlockHtmlCacheLoader($this->codec, $this->manager, $logger ?? new NullLogger());
    }

    /**
     * A save action that records what it is given
     *
     * @return callable(mixed): void
     */
    private function recordingSaver(): callable
    {
        return function (mixed $data): void {
            $this->saved[] = $data;
        };
    }

    /**
     * A collect action that adds the block's stylesheets and renders its HTML
     *
     * @param string $html
     * @return callable(): string
     */
    private function renderingCollector(string $html = '<div>block</div>'): callable
    {
        return function () use ($html): string {
            $this->manager->addStylesheet('/block.css', [], 'block_css');
            $this->manager->addStylesheet('/shared.css', [], 'shared_css');

            return $html;
        };
    }

    public function testAMissStoresTheHtmlWithExactlyTheElementsTheBlockAdded(): void
    {
        $this->manager->addStylesheet('/earlier.css', [], 'earlier_css');
        $this->manager->addStylesheet('/shared.css', [], 'shared_css');
        $wrapped = $this->subject()->wrap(
            static fn (): bool => false,
            $this->renderingCollector(),
            $this->recordingSaver()
        );

        $html = $wrapped['collector']();
        $wrapped['saver']($html);

        self::assertSame('<div>block</div>', $html);
        self::assertCount(1, $this->saved);
        self::assertIsString($this->saved[0]);
        $decoded = $this->codec->decode($this->saved[0]);
        self::assertNotNull($decoded);
        self::assertSame('<div>block</div>', $decoded['html']);
        self::assertEqualsCanonicalizing(['block_css', 'shared_css'], array_keys($decoded['elements']));
    }

    public function testAHitOnAMarkedEntryRestoresTheElementsAndAnswersThePlainHtml(): void
    {
        $this->renderingCollector()();
        $entry = $this->codec->encode('<div>cached</div>', $this->manager->getAllElements());
        $this->manager->clear();
        $wrapped = $this->subject()->wrap(
            static fn (): string => $entry,
            static fn (): string => throw new \LogicException('A hit does not render.'),
            $this->recordingSaver()
        );

        self::assertSame('<div>cached</div>', $wrapped['loader']());
        self::assertSame(['block_css', 'shared_css'], array_keys($this->manager->getAllElements()));
        self::assertInstanceOf(LinkElement::class, $this->manager->getElement('block_css'));
        self::assertSame([], $this->saved);
    }

    public function testAHitOnAnUnmarkedEntryIsAMiss(): void
    {
        $wrapped = $this->subject()->wrap(
            static fn (): string => '<div>written without the elements</div>',
            $this->renderingCollector(),
            $this->recordingSaver()
        );

        self::assertFalse($wrapped['loader']());
        self::assertSame([], $this->manager->getAllElements());
    }

    public function testAMissPassesThrough(): void
    {
        $wrapped = $this->subject()->wrap(
            static fn (): bool => false,
            $this->renderingCollector(),
            $this->recordingSaver()
        );

        self::assertFalse($wrapped['loader']());
    }

    public function testAnElementThatCannotBeAddedTurnsTheHitIntoALoggedMiss(): void
    {
        $entry = $this->codec->encode('<div>cached</div>', []);
        $codec = $this->createStub(CachedBlockHtmlCodecInterface::class);
        $codec->method('decode')->willReturn([
            'html' => '<div>cached</div>',
            'elements' => ['broken' => $this->createStub(LinkElement::class)],
        ]);
        $manager = $this->createStub(HeadTagManagerInterface::class);
        $manager->method('addElement')->willThrowException(new \RuntimeException('refused'));
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $wrapped = (new BlockHtmlCacheLoader($codec, $manager, $logger))
            ->wrap(static fn (): string => $entry, $this->renderingCollector(), $this->recordingSaver());

        self::assertFalse($wrapped['loader']());
    }

    public function testAnElementTheJsonEncodingRejectsIsLeftOutOfAMarkedEntryAndTheOthersComeBack(): void
    {
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');
        $this->codec = $this->blockHtmlCodec($logger);
        $collector = function (): string {
            $this->manager->addMetaName('description', "caf\xE9", 'description');
            $this->manager->addStylesheet('/kept.css', [], 'kept_css');

            return '<div>block</div>';
        };
        $miss = $this->subject($logger)->wrap(static fn (): bool => false, $collector, $this->recordingSaver());

        $miss['saver']($miss['collector']());

        self::assertCount(1, $this->saved);
        $entry = $this->saved[0];
        self::assertIsString($entry);
        $this->manager->clear();
        $hit = $this->subject($logger)->wrap(
            static fn (): string => $entry,
            static fn (): string => throw new \LogicException('A hit does not render.'),
            $this->recordingSaver()
        );
        self::assertSame('<div>block</div>', $hit['loader']());
        self::assertSame(['kept_css'], array_keys($this->manager->getAllElements()));
    }

    public function testWhenTheElementsCannotBeWrittenTheHtmlIsStoredWithAnEmptyMarker(): void
    {
        $codec = $this->createStub(CachedBlockHtmlCodecInterface::class);
        $codec->method('encode')->willReturnCallback(
            static function (string $html, array $elements): string {
                if ($elements !== []) {
                    throw new \RuntimeException('cannot encode');
                }

                return $html . '<!--empty-->';
            }
        );
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');
        $wrapped = (new BlockHtmlCacheLoader($codec, $this->manager, $logger))
            ->wrap(static fn (): bool => false, $this->renderingCollector(), $this->recordingSaver());

        $wrapped['saver']($wrapped['collector']());

        self::assertSame(['<div>block</div><!--empty-->'], $this->saved);
    }

    public function testAnElementKeyedByANumberComesBackOnAHit(): void
    {
        $this->manager->addStylesheet('/numbered.css', [], '123');
        $entry = $this->codec->encode('<div>cached</div>', $this->manager->getAllElements());
        $this->manager->clear();
        $wrapped = $this->subject()->wrap(
            static fn (): string => $entry,
            static fn (): string => throw new \LogicException('A hit does not render.'),
            $this->recordingSaver()
        );

        self::assertSame('<div>cached</div>', $wrapped['loader']());
        self::assertInstanceOf(LinkElement::class, $this->manager->getElement('123'));
    }

    public function testAnEntryEndingWithAForgedMarkerIsAMissAndRestoresNothing(): void
    {
        $this->renderingCollector()();
        $payload = base64_encode((string)json_encode($this->elementSerializer()->serialize(
            $this->manager->getAllElements()
        )));
        $this->manager->clear();
        $entry = '<div>imported</div><!--hryvinskyi-head-elements:1:' . $payload . '-->';
        $wrapped = $this->subject()->wrap(
            static fn (): string => $entry,
            $this->renderingCollector(),
            $this->recordingSaver()
        );

        self::assertFalse($wrapped['loader']());
        self::assertSame([], $this->manager->getAllElements());
    }

    public function testStringableHtmlIsStoredAsTextWithTheMarker(): void
    {
        $collector = function (): Phrase {
            $this->manager->addStylesheet('/phrase.css', [], 'phrase_css');

            return new Phrase('<p>%1</p>', ['translated']);
        };
        $wrapped = $this->subject()->wrap(static fn (): bool => false, $collector, $this->recordingSaver());

        $wrapped['saver']($wrapped['collector']());

        self::assertCount(1, $this->saved);
        self::assertIsString($this->saved[0]);
        $decoded = $this->codec->decode($this->saved[0]);
        self::assertNotNull($decoded);
        self::assertSame('<p>translated</p>', $decoded['html']);
        self::assertSame(['phrase_css'], array_keys($decoded['elements']));
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function rendersThatBecomeText(): array
    {
        return [
            'false' => [false, ''],
            'null' => [null, ''],
            'true' => [true, '1'],
            'an integer' => [5, '5'],
            'a float' => [1.5, '1.5'],
        ];
    }

    #[DataProvider('rendersThatBecomeText')]
    public function testARenderThatIsNoStringIsStoredAsItsTextWithTheMarker(mixed $rendered, string $text): void
    {
        $collector = function () use ($rendered): mixed {
            $this->manager->addStylesheet('/block.css', [], 'block_css');

            return $rendered;
        };
        $wrapped = $this->subject()->wrap(static fn (): bool => false, $collector, $this->recordingSaver());

        $wrapped['saver']($wrapped['collector']());

        self::assertCount(1, $this->saved);
        self::assertIsString($this->saved[0]);
        $decoded = $this->codec->decode($this->saved[0]);
        self::assertNotNull($decoded);
        self::assertSame($text, $decoded['html']);
        self::assertSame(['block_css'], array_keys($decoded['elements']));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function dataThatHasNoText(): array
    {
        return [
            'an array' => [['<div>a</div>']],
            'an object' => [new \stdClass()],
        ];
    }

    #[DataProvider('dataThatHasNoText')]
    public function testDataThatHasNoTextGoesToTheSaverUntouched(mixed $data): void
    {
        $wrapped = $this->subject()->wrap(
            static fn (): bool => false,
            static fn (): mixed => $data,
            $this->recordingSaver()
        );

        $wrapped['saver']($wrapped['collector']());

        self::assertSame([$data], $this->saved);
    }

    public function testAThrowingCollectorPropagatesAndLeavesNothingBehind(): void
    {
        $service = $this->subject();
        $throwing = $service->wrap(
            static fn (): bool => false,
            function (): never {
                $this->manager->addStylesheet('/failed.css', [], 'failed_css');
                throw new \RuntimeException('render failed');
            },
            $this->recordingSaver()
        );

        try {
            $throwing['collector']();
            self::fail('The exception must propagate.');
        } catch (\RuntimeException $e) {
            self::assertSame('render failed', $e->getMessage());
        }

        $next = $service->wrap(static fn (): bool => false, $this->renderingCollector(), $this->recordingSaver());
        $next['saver']($next['collector']());

        self::assertCount(1, $this->saved);
        self::assertIsString($this->saved[0]);
        $decoded = $this->codec->decode($this->saved[0]);
        self::assertNotNull($decoded);
        self::assertSame(['block_css', 'shared_css'], array_keys($decoded['elements']));
    }
}
