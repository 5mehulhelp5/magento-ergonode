<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Unit\Model\Pipeline;

use Ergonode\Product\Api\ProductIdentityServiceInterface;
use Ergonode\Product\Model\Data\ProductIdentity;
use Ergonode\ProductAttributeConsumer\Api\ProductAttributeSourcePreparationInterface;
use Ergonode\ProductConsumer\Api\{BatchProcessorInterface, ProductImportReadinessInterface};
use Ergonode\ProductConsumer\Model\Config\ProductImportConfig;
use Ergonode\ProductConsumer\Model\Data\ProductImportWorkItem;
use Ergonode\ProductConsumer\Model\GraphQl\RemoteProductLoader;
use Ergonode\ProductConsumer\Model\Import\{ProductImportBatch, SelectedProductImporter};
use Ergonode\ProductConsumer\Model\Magento\ProductIndexInvalidator;
use Ergonode\ProductConsumer\Model\Pipeline\{BatchContext, BatchScope, FinishBatch, LoadSources, ProductBatchPipeline, WriteProductData};
use Ergonode\ProductConsumer\Model\Port\ProductImportWorkRepositoryInterface;
use Ergonode\ProductConsumer\Model\Queue\{ProductImportConsumer, ProductImportProcessor, ProductImportQueuePublisher};
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

class PublicBatchEntryPointsTest extends TestCase
{
    public function testQueueWaitsForWholePipelineAndMarksFailedProductTerminalWithoutRunningLegacyProcessor(): void
    {
        $items = [new ProductImportWorkItem(1, 'bad', 'sync', null, 'e1', 'l', 1),
            new ProductImportWorkItem(2, 'good', 'sync', null, 'e2', 'l', 1)];
        $processor = $this->createMock(ProductImportProcessor::class);
        $processor->expects(self::never())->method('process');
        $repository = $this->createMock(ProductImportWorkRepositoryInterface::class);
        $repository->method('claim')->willReturn($items);
        $repository->expects(self::once())->method('release')->with($items[0], 'failed media', 1, 0);
        $repository->expects(self::once())->method('complete')->with($items[1])->willReturn(true);
        $repository->method('hasClaimableWork')->willReturn(false);
        $stage = new class implements BatchProcessorInterface {
            public function process(BatchContext $context): void {
                $context->run($context->entries[0], 'media', static function (): void { throw new RuntimeException('failed media'); });
            }
        };
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $finish = $this->createMock(FinishBatch::class); $finish->expects(self::once())->method('process');
        $pipeline = new ProductBatchPipeline(new BatchScope(), $finish, $logger, [], ['media' => $stage]);
        $config = $this->createMock(ProductImportConfig::class);
        $config->method('isEnabled')->willReturn(true); $config->method('getConsumerBatchSize')->willReturn(25);
        $config->method('getLeaseSeconds')->willReturn(900); $config->expects(self::never())->method('getMaximumAttempts');
        $indexes = $this->createMock(ProductIndexInvalidator::class); $indexes->expects(self::never())->method('invalidate');
        (new ProductImportConsumer($repository, $processor, $this->createStub(ProductImportQueuePublisher::class),
            $config, $indexes, new NullLogger(), $pipeline))->process('drain');
    }

    public function testAdminMissingMappingDoesNotAbortOtherSelectedProductsAndUsesSharedDataStage(): void
    {
        $identity = new ProductIdentity(13, 'MAG-13', 'ERGO-13', 'shared');
        $service = $this->createStub(ProductIdentityServiceInterface::class);
        $service->method('getIdentitiesByProductIds')->willReturn([13 => $identity]);
        $source = new RemoteProduct('ERGO-13', 'simple', 'template', false, [], []);
        $loader = $this->createMock(RemoteProductLoader::class);
        $loader->expects(self::once())->method('load')->with('ERGO-13')->willReturn($source);
        $selected = $this->createMock(SelectedProductImporter::class);
        $selected->expects(self::never())->method('import');
        $selected->expects(self::once())->method('importSource')->with($identity, $source);
        $data = new WriteProductData($this->createStub(ProductImportProcessor::class), $selected);
        $finish = $this->createMock(FinishBatch::class); $finish->expects(self::once())->method('process');
        $pipeline = new ProductBatchPipeline(new BatchScope(), $finish, new NullLogger(),
            ['load' => new LoadSources($loader)], ['data' => $data]);
        $readiness = $this->createStub(ProductImportReadinessInterface::class);
        $readiness->method('getStatus')->willReturn(['ready' => true, 'message' => '']);
        $results = (new ProductImportBatch($this->createStub(ProductAttributeSourcePreparationInterface::class),
            $readiness, $service, $selected, new NullLogger(), $pipeline))->import([12, 13]);
        self::assertSame(['failed', 'success'], array_column($results, 'status'));
        self::assertSame([12, 13], array_column($results, 'product_id'));
        self::assertStringContainsString('Log reference:', $results[0]['message']);
    }
}
