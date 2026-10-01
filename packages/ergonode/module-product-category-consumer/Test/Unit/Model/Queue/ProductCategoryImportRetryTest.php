<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryConsumer\Test\Unit\Model\Queue;

use Ergonode\Core\Api\GraphQlQueryClientInterface;
use Ergonode\ProductCategoryConsumer\Model\GraphQl\RemoteProductCategoryCodeLoader;
use Ergonode\ProductConsumer\Model\Config\ProductImportConfig;
use Ergonode\ProductConsumer\Model\Data\ProductImportWorkItem;
use Ergonode\ProductConsumer\Model\Magento\ProductIndexInvalidator;
use Ergonode\ProductConsumer\Model\Port\ProductImportWorkRepositoryInterface;
use Ergonode\ProductConsumer\Model\Queue\ProductImportConsumer;
use Ergonode\ProductConsumer\Model\Queue\ProductImportProcessor;
use Ergonode\ProductConsumer\Model\Queue\ProductImportQueuePublisher;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ProductCategoryImportRetryTest extends TestCase
{
    public function testIncompleteCategoryResponseReleasesWorkWithoutCompletingIt(): void
    {
        $client = $this->createMock(GraphQlQueryClientInterface::class);
        $client->expects(self::once())->method('query')->willReturn([
            'product' => ['sku' => 'ERG-1', 'categoryList' => ['edges' => null, 'pageInfo' => null]],
        ]);
        try {
            (new RemoteProductCategoryCodeLoader($client))->load('ERG-1');
            self::fail('Incomplete category data must fail.');
        } catch (LocalizedException $exception) {
            $item = new ProductImportWorkItem(
                1,
                'ERG-1',
                ProductImportWorkItem::OPERATION_SYNCHRONIZE,
                null,
                'event',
                'lease',
                1
            );
            $repository = $this->createMock(ProductImportWorkRepositoryInterface::class);
            $repository->expects(self::once())->method('claim')->with(25, 900)->willReturn([$item]);
            $repository->expects(self::never())->method('complete');
            $repository->expects(self::once())->method('release')->with($item, $exception->getMessage(), 8, 5, false);
            $repository->expects(self::once())->method('hasClaimableWork')->willReturn(false);
            $processor = $this->createMock(ProductImportProcessor::class);
            $processor->expects(self::once())->method('process')->with($item)->willThrowException($exception);
            $queue = $this->createMock(ProductImportQueuePublisher::class);
            $queue->expects(self::never())->method('dispatch');
            $config = $this->createMock(ProductImportConfig::class);
            $config->expects(self::once())->method('isEnabled')->willReturn(true);
            $config->expects(self::once())->method('getConsumerBatchSize')->willReturn(25);
            $config->expects(self::once())->method('getLeaseSeconds')->willReturn(900);
            $config->expects(self::once())->method('getMaximumAttempts')->willReturn(8);
            $indexer = $this->createMock(ProductIndexInvalidator::class);
            $indexer->expects(self::never())->method('invalidate');
            $logger = $this->createMock(LoggerInterface::class);
            $logger->expects(self::once())->method('error');

            (new ProductImportConsumer($repository, $processor, $queue, $config, $indexer, $logger))->process('drain');
        }
    }
}
