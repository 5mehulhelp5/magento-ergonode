<?php

declare(strict_types=1);

namespace Ergonode\ProductMediaConsumer\Test\Unit\Model\Media;

use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;
use Ergonode\ProductMedia\Api\GalleryConfigurationInterface;
use Ergonode\ProductMedia\Api\GalleryRulesInterface;
use Ergonode\ProductMedia\Api\UnmanagedImagesMode;
use Ergonode\ProductMediaConsumer\Model\Media\MediaImportHashProvider;
use PHPUnit\Framework\TestCase;
use Ergonode\ProductMedia\Exception\InvalidMediaConfigurationException;
use Ergonode\ProductConsumer\Exception\NonRetryableImportException;

class MediaImportHashProviderTest extends TestCase
{
    public function testRuleChangesInvalidateUnchangedRemoteProduct(): void
    {
        $configuration = $this->createStub(GalleryConfigurationInterface::class);
        $configuration->method('isSynchronizationEnabled')->willReturn(true);
        $configuration->method('getGalleryAttributeCode')->willReturn('photos');
        $configuration->method('getUnmanagedImagesMode')->willReturn(UnmanagedImagesMode::Keep);
        $rules = $this->createStub(GalleryRulesInterface::class);
        $rules->method('getAdditionalImages')->willReturnOnConsecutiveCalls(['back' => 2], ['back' => 3]);
        $provider = new MediaImportHashProvider($configuration, $rules);
        $source = new RemoteProduct('sku', 'simple', 'template', false, [], []);
        self::assertNotSame($provider->getHash($source), $provider->getHash($source));
    }

    public function testEquivalentRuleOrderDoesNotInvalidateImport(): void
    {
        $configuration = $this->createStub(GalleryConfigurationInterface::class);
        $configuration->method('isSynchronizationEnabled')->willReturn(true);
        $configuration->method('getUnmanagedImagesMode')->willReturn(UnmanagedImagesMode::Keep);
        $rules = $this->createStub(GalleryRulesInterface::class);
        $rules->method('getAdditionalImages')->willReturnOnConsecutiveCalls(
            ['back' => 2, 'front' => 3], ['front' => 3, 'back' => 2]
        );
        $provider = new MediaImportHashProvider($configuration, $rules);
        $source = new RemoteProduct('sku', 'simple', 'template', false, [], []);
        self::assertSame($provider->getHash($source), $provider->getHash($source));
    }
    public function testInvalidMediaConfigurationCannotRequestProductImportRetries(): void
    {
        $configuration = $this->createStub(GalleryConfigurationInterface::class);
        $configuration->method('isSynchronizationEnabled')->willReturn(true);
        $rules = $this->createStub(GalleryRulesInterface::class);
        $rules->method('getAdditionalImages')->willThrowException(new InvalidMediaConfigurationException(__('Invalid image rules.')));
        $this->expectException(NonRetryableImportException::class);
        $this->expectExceptionMessage('Invalid image rules.');
        (new MediaImportHashProvider($configuration, $rules))->getHash(new RemoteProduct('sku', 'simple', 'template', false, [], []));
    }

    public function testDisabledGalleryDoesNotValidateUnusedImageSettings(): void
    {
        $configuration = $this->createMock(GalleryConfigurationInterface::class);
        $configuration->method('isSynchronizationEnabled')->willReturn(false);
        $configuration->expects(self::never())->method('getUnmanagedImagesMode');
        $rules = $this->createMock(GalleryRulesInterface::class);
        $rules->expects(self::never())->method('getAdditionalImages');
        $rules->expects(self::never())->method('getAdditionalRole');
        self::assertNotEmpty((new MediaImportHashProvider($configuration, $rules))
            ->getHash(new RemoteProduct('sku', 'simple', 'template', false, [], [])));
    }

}
