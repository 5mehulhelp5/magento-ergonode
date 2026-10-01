<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Test\Unit\Model\Import;

use Ergonode\AttributeConsumer\Api\AttributeDefinitionSynchronizationInterface;
use Ergonode\AttributeConsumer\Model\Import\AttributeCacheRefresher;
use Ergonode\AttributeConsumer\Model\Import\OptionBatchImporter;
use Ergonode\AttributeConsumer\Model\Import\OptionCacheReconciler;
use Ergonode\Core\Api\PaginatedImporterRefresherInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class AttributeCacheRefresherTest extends TestCase
{
    public function testWriteScopeAttributeRefreshUsesWriteScopeImporter(): void
    {
        $attributeImporter = $this->createMock(AttributeDefinitionSynchronizationInterface::class);
        $attributeImporter->expects(self::once())->method('synchronize')->with(true, true);
        $paginatedRefresher = $this->createMock(PaginatedImporterRefresherInterface::class);
        $paginatedRefresher->expects(self::never())->method('refresh');
        $refresher = new AttributeCacheRefresher(
            $attributeImporter,
            $this->createStub(OptionBatchImporter::class),
            $paginatedRefresher,
            $this->createStub(OptionCacheReconciler::class)
        );

        $refresher->refreshAttributesWriteScope();
    }

    public function testWriteScopeOptionRefreshUsesWriteScopeImporter(): void
    {
        $optionImporter = $this->createMock(OptionBatchImporter::class);
        $optionImporter->expects(self::never())->method('import');
        $optionImporter->expects(self::once())
            ->method('importWriteScope')
            ->with('color', null, null, 0)
            ->willReturn([
                'has_more' => false,
                'cursor' => null,
                'processed' => 0,
                'option_codes' => [],
            ]);
        $paginatedRefresher = $this->createMock(PaginatedImporterRefresherInterface::class);
        $paginatedRefresher->expects(self::once())
            ->method('refresh')
            ->willReturnCallback(static function (callable $importPage): void {
                self::assertFalse($importPage(null)['has_more']);
            });
        $refresher = new AttributeCacheRefresher(
            $this->createStub(AttributeDefinitionSynchronizationInterface::class),
            $optionImporter,
            $paginatedRefresher,
            $this->createStub(OptionCacheReconciler::class)
        );

        $refresher->refreshOptionsWriteScope('color');
    }

    public function testReconcilesOnlyAfterAllOptionPagesAndPreservesGlobalPositions(): void
    {
        $calls = [];
        $optionImporter = $this->createMock(OptionBatchImporter::class);
        $optionImporter->expects(self::exactly(2))
            ->method('import')
            ->willReturnCallback(static function (
                string $attributeCode,
                ?string $cursor,
                ?int $pageSize,
                int $positionOffset
            ) use (&$calls): array {
                $calls[] = [$attributeCode, $cursor, $pageSize, $positionOffset];

                return $cursor === null
                    ? [
                        'has_more' => true,
                        'cursor' => 'next',
                        'processed' => 2,
                        'option_codes' => ['red', 'green'],
                    ]
                    : [
                        'has_more' => false,
                        'cursor' => null,
                        'processed' => 1,
                        'option_codes' => ['blue'],
                    ];
            });
        $paginatedRefresher = $this->createMock(PaginatedImporterRefresherInterface::class);
        $paginatedRefresher->expects(self::once())
            ->method('refresh')
            ->willReturnCallback(static function (callable $importPage): void {
                $first = $importPage(null);
                self::assertTrue($first['has_more']);
                $second = $importPage((string)$first['cursor']);
                self::assertFalse($second['has_more']);
            });
        $reconciler = $this->createMock(OptionCacheReconciler::class);
        $reconciler->expects(self::once())
            ->method('reconcile')
            ->with('color', ['red', 'green', 'blue']);
        $refresher = new AttributeCacheRefresher(
            $this->createStub(AttributeDefinitionSynchronizationInterface::class),
            $optionImporter,
            $paginatedRefresher,
            $reconciler
        );

        $refresher->refreshOptions('color');

        self::assertSame([
            ['color', null, null, 0],
            ['color', 'next', null, 2],
        ], $calls);
    }
    public function testDoesNotPruneWhenALaterPageRepeatsAnOption(): void
    {
        $importer = $this->createStub(OptionBatchImporter::class);
        $importer->method('import')->willReturn(['processed' => 1, 'option_codes' => ['red']]);
        $pages = $this->createStub(PaginatedImporterRefresherInterface::class);
        $pages->method('refresh')->willReturnCallback(static function (callable $page): void {
            $page(null);
            $page('next');
        });
        $reconciler = $this->createMock(OptionCacheReconciler::class);
        $reconciler->expects(self::never())->method('reconcile');
        $this->expectException(LocalizedException::class);
        (new AttributeCacheRefresher(
            $this->createStub(AttributeDefinitionSynchronizationInterface::class),
            $importer,
            $pages,
            $reconciler
        ))->refreshOptions('color');
    }
}
