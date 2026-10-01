<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeAdminUi\Test\Unit\Model\Mapping;

use Ergonode\CategoryAttribute\Api\AttributeMappingWriterInterface;
use Ergonode\CategoryAttribute\Api\MagentoAttributeProviderInterface;
use Ergonode\CategoryAttribute\Api\MappingReaderInterface;
use Ergonode\CategoryAttributeAdminUi\Api\SourceMetadataProviderInterface;
use Ergonode\CategoryAttributeAdminUi\Model\Mapping\AttributeMappingSaver;
use Ergonode\CategoryAttributeAdminUi\Model\Mapping\SaveContext;
use Ergonode\CategoryAttributeAdminUi\Model\Mapping\SourceMetadata;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class AttributeMappingSaverTest extends TestCase
{
    public function testBothSelectCardsAreResolvedFromTrustedMetadataInEveryComposition(): void
    {
        $left = ['code' => 'color', 'label' => 'Color', 'type' => 'select'];
        $right = ['code' => 'test_category_color', 'type' => 'select'];
        foreach ([[], ['consumer'], ['publisher'], ['consumer', 'publisher']] as $adapters) {
            $reader = $this->createStub(MappingReaderInterface::class);
            $reader->method('getAttributeRows')->willReturn([[
                'mapping_id' => 1, 'ergonode_attribute_code' => 'color', 'ergonode_type' => 'select',
            ]]);
            $providers = [];
            foreach ($adapters as $adapter) {
                $provider = $this->createStub(SourceMetadataProviderInterface::class);
                $provider->method('getAttributes')->willReturn([$left]);
                $providers[$adapter] = $provider;
            }
            $source = new SourceMetadata($reader, $providers);
            $target = $this->createStub(MagentoAttributeProviderInterface::class);
            $target->method('getAttributeMap')->willReturn(['test_category_color' => $right]);
            $writer = $this->createMock(AttributeMappingWriterInterface::class);
            $writer->expects(self::once())->method('save')->willReturnCallback(
                static function (array $resolved): array {
                    self::assertSame('select', $resolved[0]['left']['type']);
                    self::assertSame('select', $resolved[0]['right']['type']);
                    self::assertSame('color', $resolved[0]['left']['code']);
                    self::assertSame('test_category_color', $resolved[0]['right']['code']);
                    return ['inserted' => 1];
                }
            );
            $saver = new AttributeMappingSaver($source, $target, $writer, new SaveContext());
            self::assertSame(['inserted' => 1], $saver->save([[
                'left' => ['code' => 'color', 'type' => 'forged'],
                'right' => ['code' => 'test_category_color', 'type' => 'forged'],
            ]], []));
        }
    }

    public function testPendingCreationIsRejectedWithoutPublisher(): void
    {
        $source = $this->createStub(SourceMetadata::class);
        $source->method('getAttributes')->willReturn([]);
        $target = $this->createStub(MagentoAttributeProviderInterface::class);
        $target->method('getAttributeMap')->willReturn([]);
        $writer = $this->createMock(AttributeMappingWriterInterface::class);
        $writer->expects(self::never())->method('save');
        $saver = new AttributeMappingSaver($source, $target, $writer, new SaveContext());
        $this->expectException(LocalizedException::class);
        $saver->save([['left' => ['code' => 'new_color', 'pending_create' => true]]], []);
    }

    public function testMissingSourceIsNotRestoredFromStaleMappingWhenProviderIsInstalled(): void
    {
        $reader = $this->createMock(MappingReaderInterface::class);
        $reader->expects(self::never())->method('getAttributeRows');
        $provider = $this->createStub(SourceMetadataProviderInterface::class);
        $provider->method('getAttributes')->willReturn([]);
        self::assertSame([], (new SourceMetadata($reader, [$provider]))->getAttributes());
    }
}
