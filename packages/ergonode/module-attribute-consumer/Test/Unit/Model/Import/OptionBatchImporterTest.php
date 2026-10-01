<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Test\Unit\Model\Import;

use Ergonode\AttributeConsumer\Model\Import\OptionBatchImporter;
use Ergonode\AttributeConsumer\Model\Import\ImportPageValidator;
use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\Core\Api\PageQueryRetrierInterface;
use Ergonode\Core\Model\GraphQl\Client;
use Ergonode\AttributeConsumer\Model\Import\AttributeCacheWriter;
use Ergonode\AttributeConsumer\Model\Import\AttributeNormalizer;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class OptionBatchImporterTest extends TestCase
{
    public function testWriteScopeImportNeverUsesReadQuery(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::never())->method('query');
        $client->expects(self::once())
            ->method('queryWriteScope')
            ->willReturn([
                'attributeOptionList' => [
                    'edges' => [],
                    'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                ],
            ]);
        $cacheWriter = $this->createMock(AttributeCacheWriter::class);
        $cacheWriter->expects(self::once())->method('saveOptions')->with('color', [])->willReturn([]);
        $retrier = $this->createMock(PageQueryRetrierInterface::class);
        $retrier->expects(self::once())
            ->method('query')
            ->willReturnCallback(static function (array $pageSizes, int $pageSize, callable $query): array {
                return [...$query($pageSize), '_page_size' => $pageSize];
            });
        $importer = new OptionBatchImporter(
            $client,
            $this->createStub(LanguageStoreMappingProviderInterface::class),
            $this->createStub(AttributeNormalizer::class),
            $cacheWriter,
            $this->createStub(ChangeReport::class),
            new ImportPageValidator(),
            $retrier
        );

        self::assertFalse($importer->importWriteScope('color')['has_more']);
    }

    public function testFreshImportStoresFetchedOptions(): void
    {
        $cacheWriter = $this->createMock(AttributeCacheWriter::class);
        $cacheWriter->expects(self::once())->method('saveOptions')->with('color', [])->willReturn([]);
        $retrier = $this->createStub(PageQueryRetrierInterface::class);
        $retrier->method('query')->willReturn([
            'attributeOptionList' => ['edges' => [], 'pageInfo' => ['hasNextPage' => false]],
            '_page_size' => 200,
        ]);
        $importer = new OptionBatchImporter(
            $this->createStub(Client::class),
            $this->createStub(LanguageStoreMappingProviderInterface::class),
            $this->createStub(AttributeNormalizer::class),
            $cacheWriter,
            $this->createStub(ChangeReport::class),
            new ImportPageValidator(),
            $retrier
        );

        self::assertFalse($importer->import('color')['has_more']);
    }

    public function testRejectsIncompleteResponseBeforeWritingCache(): void
    {
        $cacheWriter = $this->createMock(AttributeCacheWriter::class);
        $cacheWriter->expects(self::never())->method('saveOptions');
        $retrier = $this->createStub(PageQueryRetrierInterface::class);
        $retrier->method('query')->willReturn([
            'attributeOptionList' => ['edges' => []],
            '_page_size' => 200,
        ]);
        $importer = new OptionBatchImporter(
            $this->createStub(Client::class),
            $this->createStub(LanguageStoreMappingProviderInterface::class),
            $this->createStub(AttributeNormalizer::class),
            $cacheWriter,
            $this->createStub(ChangeReport::class),
            new ImportPageValidator(),
            $retrier
        );

        $this->expectException(LocalizedException::class);
        $importer->import('color');
    }

    public function testStoresGlobalOptionPositionsForLaterPages(): void
    {
        $normalizer = $this->createMock(AttributeNormalizer::class);
        $normalizer->expects(self::exactly(2))
            ->method('normalizeOption')
            ->willReturnCallback(static function (array $node, int $position): array {
                self::assertSame($node['expected_position'], $position);

                return [
                    'code' => $node['code'],
                    'labels' => [],
                    'sort_order' => $position,
                    'raw' => $node,
                    'hash' => 'hash-' . $position,
                ];
            });
        $cacheWriter = $this->createMock(AttributeCacheWriter::class);
        $cacheWriter->expects(self::once())
            ->method('saveOptions')
            ->with(
                'color',
                self::callback(static function (array $options): bool {
                    self::assertSame([201, 202], array_column($options, 'sort_order'));

                    return true;
                })
            )
            ->willReturn(['blue' => 'updated', 'red' => 'updated']);
        $retrier = $this->createStub(PageQueryRetrierInterface::class);
        $retrier->method('query')->willReturn([
            'attributeOptionList' => [
                'edges' => [
                    ['node' => ['code' => 'blue', 'expected_position' => 201]],
                    ['node' => ['code' => 'red', 'expected_position' => 202]],
                ],
                'pageInfo' => ['hasNextPage' => false],
            ],
            '_page_size' => 200,
        ]);
        $importer = new OptionBatchImporter(
            $this->createStub(Client::class),
            $this->createStub(LanguageStoreMappingProviderInterface::class),
            $normalizer,
            $cacheWriter,
            $this->createStub(ChangeReport::class),
            new ImportPageValidator(),
            $retrier
        );

        $result = $importer->import('color', 'cursor', 200, 200);

        self::assertSame(2, $result['processed']);
        self::assertSame(['blue', 'red'], $result['option_codes']);
    }
}
