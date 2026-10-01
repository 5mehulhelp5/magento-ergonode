<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Test\Unit\Model\Import;

use Ergonode\AttributeConsumer\Model\Import\AttributeBatchImporter;
use Ergonode\AttributeConsumer\Model\Import\ImportPageValidator;
use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\Core\Api\PageQueryRetrierInterface;
use Ergonode\Core\Model\GraphQl\Client;
use Ergonode\AttributeConsumer\Model\Import\AttributeCacheWriter;
use Ergonode\AttributeConsumer\Model\Import\AttributeNormalizer;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class AttributeBatchImporterTest extends TestCase
{
    public function testImportUsesOnlyReadScope(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::never())->method('queryWriteScope');
        $client->expects(self::once())
            ->method('query')
            ->willReturn([
                'attributeStream' => [
                    'edges' => [],
                    'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                ],
            ]);
        $cacheWriter = $this->createMock(AttributeCacheWriter::class);
        $cacheWriter->expects(self::once())->method('saveAttributes')->with([])->willReturn([]);
        $retrier = $this->createMock(PageQueryRetrierInterface::class);
        $retrier->expects(self::once())
            ->method('query')
            ->willReturnCallback(static function (array $pageSizes, int $pageSize, callable $query): array {
                return [...$query($pageSize), '_page_size' => $pageSize];
            });
        $importer = new AttributeBatchImporter(
            $client,
            $this->createStub(LanguageStoreMappingProviderInterface::class),
            $this->createStub(AttributeNormalizer::class),
            $cacheWriter,
            $this->createStub(ChangeReport::class),
            new ImportPageValidator(),
            $retrier
        );

        self::assertFalse($importer->import()['has_more']);
    }

    public function testFreshImportStoresFetchedAttributes(): void
    {
        $cacheWriter = $this->createMock(AttributeCacheWriter::class);
        $cacheWriter->expects(self::once())->method('saveAttributes')->with([])->willReturn([]);
        $retrier = $this->createStub(PageQueryRetrierInterface::class);
        $retrier->method('query')->willReturn([
            'attributeStream' => [
                'edges' => [],
                'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
            ],
            '_page_size' => 200,
        ]);
        $importer = new AttributeBatchImporter(
            $this->createStub(Client::class),
            $this->createStub(LanguageStoreMappingProviderInterface::class),
            $this->createStub(AttributeNormalizer::class),
            $cacheWriter,
            $this->createStub(ChangeReport::class),
            new ImportPageValidator(),
            $retrier
        );

        self::assertFalse($importer->import()['has_more']);
    }

    public function testReturnsTerminalCursorWhenImportCompletes(): void
    {
        $cacheWriter = $this->createMock(AttributeCacheWriter::class);
        $cacheWriter->expects(self::once())->method('saveAttributes')->with([])->willReturn([]);
        $retrier = $this->createStub(PageQueryRetrierInterface::class);
        $retrier->method('query')->willReturn([
            'attributeStream' => [
                'edges' => [],
                'pageInfo' => ['hasNextPage' => false, 'endCursor' => 'terminal'],
            ],
            '_page_size' => 100,
        ]);
        $importer = new AttributeBatchImporter(
            $this->createStub(Client::class),
            $this->createStub(LanguageStoreMappingProviderInterface::class),
            $this->createStub(AttributeNormalizer::class),
            $cacheWriter,
            $this->createStub(ChangeReport::class),
            new ImportPageValidator(),
            $retrier
        );

        $result = $importer->import('previous', 100);

        self::assertFalse($result['has_more']);
        self::assertSame('terminal', $result['cursor']);
        self::assertSame(100, $result['page_size']);
    }

    public function testRejectsResponseWithoutPaginationMetadata(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('missing pagination metadata');

        $this->importerFor([
            'attributeStream' => ['edges' => []],
            '_page_size' => 200,
        ])->import();
    }

    public function testRejectsNextPageWithoutCursor(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('no cursor for the next page');

        $this->importerFor([
            'attributeStream' => [
                'edges' => [],
                'pageInfo' => ['hasNextPage' => true, 'endCursor' => null],
            ],
            '_page_size' => 200,
        ])->import();
    }

    public function testRejectsResponseWithoutEffectivePageSize(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('invalid page size');

        $this->importerFor([
            'attributeStream' => [
                'edges' => [],
                'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
            ],
        ])->import();
    }

    /** @param array<string, mixed> $data */
    private function importerFor(array $data): AttributeBatchImporter
    {
        $cacheWriter = $this->createMock(AttributeCacheWriter::class);
        $cacheWriter->expects(self::never())->method('saveAttributes');
        $retrier = $this->createStub(PageQueryRetrierInterface::class);
        $retrier->method('query')->willReturn($data);

        return new AttributeBatchImporter(
            $this->createStub(Client::class),
            $this->createStub(LanguageStoreMappingProviderInterface::class),
            $this->createStub(AttributeNormalizer::class),
            $cacheWriter,
            $this->createStub(ChangeReport::class),
            new ImportPageValidator(),
            $retrier
        );
    }
}
