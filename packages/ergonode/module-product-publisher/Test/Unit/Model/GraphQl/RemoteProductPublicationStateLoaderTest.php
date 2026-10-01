<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\Unit\Model\GraphQl;

use Ergonode\Core\Api\GraphQlWriteScopeQueryClientInterface;
use Ergonode\ProductPublisher\Model\GraphQl\RemoteProductPublicationStateLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

class RemoteProductPublicationStateLoaderTest extends TestCase
{
    public function testLoadsAllPagesAndKeepsNumericSkuAndLanguagePresence(): void
    {
        $client = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $calls = 0;
        $client->expects(self::exactly(2))->method('queryWriteScope')->willReturnCallback(
            function (string $query, array $variables) use (&$calls): array {
                ++$calls;
                self::assertStringStartsWith('query ProductPublicationComparison', $query);
                self::assertStringContainsString('translations { language }', $query);
                if ($calls === 1) {
                    self::assertSame(
                        ['sku_0' => '001', 'after_0' => null, 'sku_1' => '2', 'after_1' => null],
                        $variables
                    );
                    return [
                        'product_0' => $this->product('001', [$this->attribute('title', ['en_GB'])], true, 'next'),
                        'product_1' => $this->product('2'),
                    ];
                }
                self::assertSame(['sku_0' => '001', 'after_0' => 'next'], $variables);
                return ['product_0' => $this->product('001', [$this->attribute('title', ['pl_PL'])])];
            }
        );

        $result = (new RemoteProductPublicationStateLoader($client, new NullLogger()))->load(['001', '2', '001']);

        self::assertSame([
            'sku:001' => ['template' => 'default', 'translations' => ['title' => ['en_GB' => true, 'pl_PL' => true]]],
            'sku:2' => ['template' => 'default', 'translations' => []],
        ], $result);
    }

    public function testLimitsQueriesToFiftyProductsAndDoesNotRetainSnapshotsAcrossCalls(): void
    {
        $client = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $sizes = [];
        $client->expects(self::exactly(3))->method('queryWriteScope')->willReturnCallback(
            function (string $_query, array $variables) use (&$sizes): array {
                $sizes[] = count($variables) / 2;
                $data = [];
                foreach (array_chunk($variables, 2) as $index => [$sku]) {
                    $data['product_' . $index] = $this->product($sku);
                }
                return $data;
            }
        );
        $loader = new RemoteProductPublicationStateLoader($client, new NullLogger());
        self::assertCount(51, $loader->load(array_map('strval', range(1, 51))));
        self::assertCount(1, $loader->load(['1']));
        self::assertSame([50, 1, 1], $sizes);
        self::assertSame([], $loader->load([]));
    }

    #[DataProvider('invalidResponses')]
    public function testIncompleteReadsCannotProveAbsence(array $response): void
    {
        $client = $this->createStub(GraphQlWriteScopeQueryClientInterface::class);
        $client->method('queryWriteScope')->willReturn($response);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(
            self::stringContains('preserving planned writes'),
            self::callback(
                static fn (array $context): bool => array_keys($context) === ['exception_class', 'product_count']
            )
        );

        self::assertSame([], (new RemoteProductPublicationStateLoader($client, $logger))->load(['001']));
    }

    public static function invalidResponses(): array
    {
        $product = [
            'sku' => '001', 'template' => ['code' => 'default'],
            'attributeList' => ['edges' => [], 'pageInfo' => ['hasNextPage' => false, 'endCursor' => null]],
        ];
        $invalid = [
            'wrong product' => ['sku' => 'other'],
            'missing template' => ['template' => null],
            'missing list' => ['attributeList' => null],
            'missing page info' => ['attributeList' => ['pageInfo' => null]],
            'invalid next page flag' => ['attributeList' => ['pageInfo' => ['hasNextPage' => 'false']]],
            'missing cursor' => ['attributeList' => ['pageInfo' => ['hasNextPage' => true]]],
            'missing translations' => ['attributeList' => ['edges' => [
                ['node' => ['attribute' => ['code' => 'name']]],
            ]]],
            'invalid language' => ['attributeList' => ['edges' => [
                ['node' => ['attribute' => ['code' => 'name'], 'translations' => [['language' => null]]]],
            ]]],
        ];
        $cases = ['missing alias' => [[]]];
        foreach ($invalid as $name => $change) {
            $cases[$name] = [['product_0' => array_replace_recursive($product, $change)]];
        }
        return $cases;
    }

    public function testRepeatedCursorDiscardsEvenTheFirstPage(): void
    {
        $client = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $client->expects(self::exactly(2))->method('queryWriteScope')
            ->willReturn(['product_0' => $this->product('001', [], true, 'same')]);
        self::assertSame([], (new RemoteProductPublicationStateLoader($client, new NullLogger()))->load(['001']));
    }

    public function testTimeoutOnSecondPageDiscardsPartialAndCompletedProductsInThatChunk(): void
    {
        $client = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $calls = 0;
        $client->expects(self::exactly(2))->method('queryWriteScope')->willReturnCallback(
            function () use (&$calls): array {
                if (++$calls === 2) {
                    throw new RuntimeException('sensitive transport details');
                }
                return ['product_0' => $this->product('001', [], true, 'next'), 'product_1' => $this->product('2')];
            }
        );
        self::assertSame([], (new RemoteProductPublicationStateLoader($client, new NullLogger()))->load(['001', '2']));
    }

    public function testProductDisappearingDuringPaginationIsNotAnEmptyProduct(): void
    {
        $client = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $client->expects(self::exactly(2))->method('queryWriteScope')->willReturnOnConsecutiveCalls(
            ['product_0' => $this->product('001', [], true, 'next')],
            ['product_0' => null]
        );
        self::assertSame([], (new RemoteProductPublicationStateLoader($client, new NullLogger()))->load(['001']));
    }

    public function testTemplateChangeDuringPaginationDisablesComparison(): void
    {
        $client = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $client->expects(self::exactly(2))->method('queryWriteScope')->willReturnOnConsecutiveCalls(
            ['product_0' => $this->product('001', [], true, 'next')],
            ['product_0' => array_replace($this->product('001'), ['template' => ['code' => 'new']])]
        );
        self::assertSame([], (new RemoteProductPublicationStateLoader($client, new NullLogger()))->load(['001']));
    }

    private function product(
        string $sku,
        array $edges = [],
        bool $hasNextPage = false,
        ?string $cursor = null
    ): array {
        return [
            'sku' => $sku,
            'template' => ['code' => 'default'],
            'attributeList' => [
                'edges' => $edges,
                'pageInfo' => ['hasNextPage' => $hasNextPage, 'endCursor' => $cursor],
            ],
        ];
    }

    private function attribute(string $code, array $languages): array
    {
        return ['node' => [
            'attribute' => ['code' => $code],
            'translations' => array_map(static fn (string $language): array => ['language' => $language], $languages),
        ]];
    }
}
