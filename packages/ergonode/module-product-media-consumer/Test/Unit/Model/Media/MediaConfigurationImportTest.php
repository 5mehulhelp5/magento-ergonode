<?php

declare(strict_types=1);

namespace Ergonode\ProductMediaConsumer\Test\Unit\Model\Media;

use Ergonode\Media\Api\GallerySchedulerInterface;
use Ergonode\Media\Model\Port\MediaRepositoryInterface;
use Ergonode\Media\Model\ValueObject\Gallery\GallerySelection;
use Ergonode\Product\Api\ProductIdentityServiceInterface;
use Ergonode\ProductConsumer\Api\ProductTypeAdapterInterface;
use Ergonode\ProductConsumer\Model\Data\PreparedProduct;
use Ergonode\ProductConsumer\Model\Data\ProductImportWorkItem;
use Ergonode\ProductConsumer\Model\GraphQl\RemoteProductLoader;
use Ergonode\ProductConsumer\Model\Magento\MagentoSkuSynchronizer;
use Ergonode\ProductConsumer\Model\Magento\ProductAttributeValueMapper;
use Ergonode\ProductConsumer\Model\Magento\ProductAttributeWriter;
use Ergonode\ProductConsumer\Model\Magento\ProductDeletionPolicy;
use Ergonode\ProductConsumer\Model\Magento\ProductStateSynchronizerPool;
use Ergonode\ProductConsumer\Model\Magento\ProductStateWriter;
use Ergonode\ProductConsumer\Model\Magento\ProductTargetPreparer;
use Ergonode\ProductConsumer\Model\Magento\ProductUrlKeyWriter;
use Ergonode\ProductConsumer\Model\Queue\ProductImportHashProviderPool;
use Ergonode\ProductConsumer\Model\Queue\ProductImportProcessor;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;
use Ergonode\ProductMedia\Api\GalleryConfigurationInterface;
use Ergonode\ProductMedia\Api\GalleryRulesInterface;
use Ergonode\ProductMedia\Api\UnmanagedImagesMode;
use Ergonode\ProductMediaConsumer\Model\Magento\ProductFileUsageSynchronizer;
use Ergonode\ProductMediaConsumer\Model\Media\MediaImportHashProvider;
use Ergonode\ProductMediaConsumer\Model\Media\ProductGallerySelectionProvider;
use Ergonode\ProductMediaConsumer\Model\Media\ProductMediaSynchronizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MediaConfigurationImportTest extends TestCase
{
    #[DataProvider('modes')]
    public function testNextImportAppliesChangedModeAndFollowingImportRemainsSkipped(
        UnmanagedImagesMode $nextMode,
        bool $changed
    ): void {
        $mode = UnmanagedImagesMode::Keep;
        $configuration = $this->createStub(GalleryConfigurationInterface::class);
        $configuration->method('isSynchronizationEnabled')->willReturn(true);
        $configuration->method('getGalleryAttributeCode')->willReturn('photos');
        $configuration->method('getUnmanagedImagesMode')->willReturnCallback(
            static function () use (&$mode): UnmanagedImagesMode { return $mode; }
        );
        $hashes = new ProductImportHashProviderPool([
            'media' => new MediaImportHashProvider($configuration, $this->createStub(GalleryRulesInterface::class)),
        ]);
        $source = new RemoteProduct('SKU-1', 'simple', 'template', false, [], []);
        $selection = new GallerySelection(['a.jpg', 'c.jpg']);
        $gallery = $this->createStub(ProductGallerySelectionProvider::class);
        $gallery->method('provide')->willReturn($selection);
        $scheduledModes = [];
        $scheduler = $this->createMock(GallerySchedulerInterface::class);
        $scheduler->expects(self::exactly($changed ? 2 : 1))->method('schedule')->with(23, $selection)
            ->willReturnCallback(static function () use (&$scheduledModes, &$mode): void {
                $scheduledModes[] = $mode;
            });
        $repository = $this->createStub(MediaRepositoryInterface::class);
        $repository->method('hasFailedWork')->willReturn(false);
        $media = new ProductMediaSynchronizer(
            $this->createStub(ProductFileUsageSynchronizer::class), $gallery, $scheduler, $repository
        );
        $mapper = $this->createStub(ProductAttributeValueMapper::class);
        $mapper->method('mapSpecial')->willReturn(['values' => [], 'clear' => []]);
        $mapper->method('map')->willReturn(['values' => [], 'clear' => []]);
        $skus = $this->createStub(MagentoSkuSynchronizer::class);
        $writer = new ProductStateWriter(
            $mapper,
            $this->createStub(ProductAttributeWriter::class),
            new ProductStateSynchronizerPool(['media' => $media]),
            $this->createStub(ProductUrlKeyWriter::class),
            $skus
        );
        $preparer = $this->createStub(ProductTargetPreparer::class);
        $preparer->method('prepare')->willReturn(new PreparedProduct(
            23, $this->createStub(ProductTypeAdapterInterface::class), 'SKU-1', 'SKU-1'
        ));
        $storedHash = null;
        $identity = $this->createMock(ProductIdentityServiceInterface::class);
        $identity->method('getImportHash')->willReturnCallback(
            static function () use (&$storedHash): ?string { return $storedHash; }
        );
        $identity->expects(self::exactly($changed ? 2 : 1))->method('recordImported')
            ->willReturnCallback(static function (int $id, string $sku, string $hash) use (&$storedHash): void {
                $storedHash = $hash;
            });
        $processor = new ProductImportProcessor(
            $this->createStub(RemoteProductLoader::class),
            $this->createStub(ProductDeletionPolicy::class),
            $preparer, $writer, $identity, $mapper, $skus, $hashes
        );
        $item = new ProductImportWorkItem(1, 'SKU-1', 'sync', null, 'event', 'lease', 0);

        self::assertTrue($processor->processSource($item, $source));
        $mode = $nextMode;
        self::assertSame($changed, $processor->processSource($item, $source));
        self::assertFalse($processor->processSource($item, $source));
        self::assertSame($changed ? [UnmanagedImagesMode::Keep, $nextMode] : [UnmanagedImagesMode::Keep], $scheduledModes);
    }

    public static function modes(): array
    {
        return [
            'unchanged keep is skipped' => [UnmanagedImagesMode::Keep, false],
            'keep to hide is applied once' => [UnmanagedImagesMode::Hide, true],
            'keep to remove is applied once' => [UnmanagedImagesMode::Remove, true],
        ];
    }
}
