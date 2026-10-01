<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Unit\Model\Mapping;

use Ergonode\Attribute\Model\ErgonodeAttributeTypeResolver;
use Ergonode\CategoryAttribute\Model\Provider\MagentoCategoryOptionProvider;
use Ergonode\CategoryAttributeConsumer\Model\Mapping\CategoryAttributeValueMappingFactory;
use Ergonode\CategoryAttributeConsumer\Model\Mapping\OptionLabelKeyNormalizer;
use PHPUnit\Framework\TestCase;

class CategoryAttributeValueMappingFactoryTest extends TestCase
{
    public function testBuildsNormalizedOptionMappingContext(): void
    {
        $magentoOptions = $this->createMock(MagentoCategoryOptionProvider::class);
        $magentoOptions->expects(self::once())
            ->method('getOptions')
            ->with('category_style')
            ->willReturn([
                ['code' => 'option_31', 'label' => 'Żółte krzesła'],
                ['code' => 'invalid', 'label' => 'Ignored'],
            ]);

        $mapping = (new CategoryAttributeValueMappingFactory(
            new ErgonodeAttributeTypeResolver(),
            $magentoOptions,
            new OptionLabelKeyNormalizer()
        ))->create([
            'mapping_id' => 7,
            'ergonode_attribute_code' => 'style',
            'magento_attribute_code' => 'category_style',
            'ergonode_type' => 'multi_select',
            'magento_type' => 'multi_select',
        ]);

        self::assertSame('multiselect', $mapping['ergonode_type']);
        self::assertSame('multiselect', $mapping['magento_type']);
        self::assertSame([], $mapping['option_labels']);
        self::assertSame(['zoltekrzesla' => 31], $mapping['magento_option_ids_by_label']);
    }
}
