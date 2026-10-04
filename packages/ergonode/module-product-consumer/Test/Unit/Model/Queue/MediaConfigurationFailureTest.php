<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Unit\Model\Queue;

use Ergonode\ProductConsumer\Exception\NonRetryableImportException;
use Ergonode\ProductConsumer\Model\Config\ProductImportConfig;
use Ergonode\ProductConsumer\Model\Data\ProductImportWorkItem;
use Ergonode\ProductConsumer\Model\Magento\ProductIndexInvalidator;
use Ergonode\ProductConsumer\Model\Port\ProductImportWorkRepositoryInterface;
use Ergonode\ProductConsumer\Model\Queue\ProductImportConsumer;
use Ergonode\ProductConsumer\Model\Queue\ProductImportProcessor;
use Ergonode\ProductConsumer\Model\Queue\ProductImportQueuePublisher;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MediaConfigurationFailureTest extends TestCase
{
    public function testInvalidConfigurationIsLoggedAndTerminalWhileOtherProductsContinue(): void
    {
        $failed = new ProductImportWorkItem(1, 'bad', 'sync', null, 'event1', 'lease1', 1);
        $next = new ProductImportWorkItem(2, 'good', 'sync', null, 'event2', 'lease2', 1);
        $error = new NonRetryableImportException(__('Invalid media setting additional_images.'));
        $repository = $this->createMock(ProductImportWorkRepositoryInterface::class);
        $repository->method('claim')->willReturn([$failed, $next]);
        $repository->expects(self::once())->method('release')->with($failed, $error->getMessage(), 1, 0);
        $repository->expects(self::once())->method('complete')->with($next);
        $repository->method('hasClaimableWork')->willReturn(false);
        $processor = $this->createMock(ProductImportProcessor::class);
        $processor->expects(self::exactly(2))->method('process')->willReturnCallback(
            static function ($item) use ($failed, $error): bool {
                if ($item === $failed) { throw $error; }
                return true;
            }
        );
        $config = $this->createMock(ProductImportConfig::class);
        $config->method('isEnabled')->willReturn(true);
        $config->expects(self::never())->method('getMaximumAttempts');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(
            'Unable to import Ergonode product: correct media configuration before a new import.',
            ['sku' => 'bad', 'exception' => $error]
        );
        $publisher = $this->createMock(ProductImportQueuePublisher::class);
        $publisher->expects(self::never())->method('dispatch');
        $indexes = $this->createMock(ProductIndexInvalidator::class);
        $indexes->expects(self::once())->method('invalidate');
        (new ProductImportConsumer($repository, $processor, $publisher, $config, $indexes, $logger))->process('drain');
    }
}
