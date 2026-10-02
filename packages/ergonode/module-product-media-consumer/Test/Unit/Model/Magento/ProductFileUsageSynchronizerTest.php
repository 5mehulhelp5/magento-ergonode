<?php

declare(strict_types=1);

namespace Ergonode\ProductMediaConsumer\Test\Unit\Model\Magento;

use Ergonode\ProductAttributeConsumer\Api\ProductAttributeMappingProviderInterface;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\Media\Api\FileUsageRecorderInterface;
use Ergonode\Media\Model\ValueObject\File\FileUsageSet;
use Ergonode\ProductConsumer\Model\ValueObject\Product\Attribute\LocalizedStringValues;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProductAttribute;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProductAttributeType;
use Ergonode\ProductMediaConsumer\Model\Magento\ProductFileUsageSynchronizer;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Model\Config;
use PHPUnit\Framework\TestCase;

class ProductFileUsageSynchronizerTest extends TestCase
{
    public function testTranslatesProductConsumerFileValueToMediaOwnedReferences(): void
    {
        $mappings = $this->createStub(ProductAttributeMappingProviderInterface::class);
        $mappings->method('getMappings')->willReturn([[
            'ergonode_attribute_code' => 'manual',
            'magento_attribute_code' => 'instruction_file',
            'ergonode_type' => 'file',
            'magento_type' => 'file',
        ]]);
        $languages = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languages->method('getLanguageStoreMap')->willReturn([0 => 'pl_PL', 2 => 'en_GB']);
        $attribute = $this->createStub(Attribute::class);
        $attribute->method('getIsGlobal')->willReturn(0);
        $eav = $this->createStub(Config::class);
        $eav->method('getAttribute')->willReturn($attribute);
        $recorder = $this->createMock(FileUsageRecorderInterface::class);
        $recorder->expects(self::once())->method('synchronize')->with(
            23,
            self::callback(static fn (FileUsageSet $set): bool => $set->toRows() === [
                [
                    'source_path' => '/manual-pl.pdf',
                    'attribute_code' => 'instruction_file',
                    'store_id' => 0,
                ],
                [
                    'source_path' => '/manual-en.pdf',
                    'attribute_code' => 'instruction_file',
                    'store_id' => 2,
                ],
            ])
        );

        (new ProductFileUsageSynchronizer($mappings, $languages, $recorder, $eav))->synchronize(23, [
            new RemoteProductAttribute(
                'manual',
                new RemoteProductAttributeType('file'),
                new LocalizedStringValues([
                    'pl_PL' => '/manual-pl.pdf',
                    'en_GB' => '/manual-en.pdf',
                ])
            ),
        ]);
    }
}
