<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Unit\Model\Import;

use Ergonode\ProductAttributeConsumer\Api\ProductAttributeSourcePreparationInterface;

use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Ergonode\Product\Api\ProductIdentityServiceInterface;
use Ergonode\ProductConsumer\Api\ProductImportReadinessInterface;
use Ergonode\ProductConsumer\Model\Import\ProductImportBatch;
use Ergonode\ProductConsumer\Model\Import\SelectedProductImporter;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ProductImportBatchTest extends TestCase
{
    public function testOneFailureDoesNotSkipRemainingProductsAndIncludesContext(): void
    {
        $readiness = $this->createStub(ProductImportReadinessInterface::class);
        $readiness->method('getStatus')->willReturn(['ready' => true, 'message' => '']);
        $identities = $this->createStub(ProductIdentityServiceInterface::class);
        $first = $this->createStub(ProductIdentityInterface::class);
        $first->method('getMagentoSku')->willReturn('MAG-1');
        $first->method('getErgonodeSku')->willReturn('ERGO-1');
        $second = $this->createStub(ProductIdentityInterface::class);
        $identities->method('getIdentitiesByProductIds')->willReturn([1 => $first, 2 => $second]);
        $importer = $this->createMock(SelectedProductImporter::class);
        $importer->expects(self::exactly(2))->method('import')->willReturnCallback(
            static function (ProductIdentityInterface $identity) use ($first): void {
                if ($identity === $first) {
                    throw new LocalizedException(__('Missing option mapping.'));
                }
            }
        );
        $results = (new ProductImportBatch(
            $this->createMock(ProductAttributeSourcePreparationInterface::class),
            $readiness,
            $identities,
            $importer,
            new NullLogger()
        ))->import([1, 2, 3]);
        self::assertSame(['failed', 'success', 'failed'], array_column($results, 'status'));
        self::assertStringContainsString('Missing option mapping.', $results[0]['message']);
        self::assertStringContainsString('ERGO-1', $results[0]['message']);
        self::assertStringContainsString('Log reference:', $results[0]['message']);
        self::assertStringContainsString('no Ergonode SKU mapping', $results[2]['message']);
    }

    public function testUnavailableConfigurationPreventsProductWrites(): void
    {
        $readiness = $this->createStub(ProductImportReadinessInterface::class);
        $readiness->method('getStatus')->willReturn(['ready' => false, 'message' => 'Configure languages.']);
        $importer = $this->createMock(SelectedProductImporter::class);
        $importer->expects(self::never())->method('import');
        $batch = new ProductImportBatch(
            $this->createMock(ProductAttributeSourcePreparationInterface::class),
            $readiness,
            $this->createStub(ProductIdentityServiceInterface::class),
            $importer,
            new NullLogger()
        );
        $this->expectException(LocalizedException::class);
        $batch->import([1]);
    }

    public function testOversizedBatchIsRejectedBeforeLoadingAnyProduct(): void
    {
        $identities = $this->createMock(ProductIdentityServiceInterface::class);
        $identities->expects(self::never())->method('getIdentitiesByProductIds');
        $batch = new ProductImportBatch(
            $this->createMock(ProductAttributeSourcePreparationInterface::class),
            $this->createStub(ProductImportReadinessInterface::class),
            $identities,
            $this->createStub(SelectedProductImporter::class),
            new NullLogger()
        );
        $this->expectException(LocalizedException::class);
        $batch->import(range(1, 51));
    }
}
