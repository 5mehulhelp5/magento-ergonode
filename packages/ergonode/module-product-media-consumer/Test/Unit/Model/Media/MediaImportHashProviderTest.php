<?php

declare(strict_types=1);

namespace Ergonode\ProductMediaConsumer\Test\Unit\Model\Media;

use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;
use Ergonode\ProductMedia\Api\GalleryConfigurationInterface;
use Ergonode\ProductMedia\Api\GalleryRulesInterface;
use Ergonode\ProductMediaConsumer\Model\Media\MediaImportHashProvider;
use PHPUnit\Framework\TestCase;

class MediaImportHashProviderTest extends TestCase
{
    public function testRuleChangesInvalidateUnchangedRemoteProduct(): void
    {
        $configuration = $this->createStub(GalleryConfigurationInterface::class);
        $configuration->method('isSynchronizationEnabled')->willReturn(true);
        $configuration->method('getGalleryAttributeCode')->willReturn('photos');
        $rules = $this->createStub(GalleryRulesInterface::class);
        $rules->method('getAdditionalImages')->willReturnOnConsecutiveCalls(['back' => 2], ['back' => 3]);
        $provider = new MediaImportHashProvider($configuration, $rules);
        $source = new RemoteProduct('sku', 'simple', 'template', false, [], []);
        self::assertNotSame($provider->getHash($source), $provider->getHash($source));
    }

    public function testEquivalentRuleOrderDoesNotInvalidateImport(): void
    {
        $configuration = $this->createStub(GalleryConfigurationInterface::class);
        $rules = $this->createStub(GalleryRulesInterface::class);
        $rules->method('getAdditionalImages')->willReturnOnConsecutiveCalls(
            ['back' => 2, 'front' => 3], ['front' => 3, 'back' => 2]
        );
        $provider = new MediaImportHashProvider($configuration, $rules);
        $source = new RemoteProduct('sku', 'simple', 'template', false, [], []);
        self::assertSame($provider->getHash($source), $provider->getHash($source));
    }
}
