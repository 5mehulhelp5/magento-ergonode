<?php

declare(strict_types=1);

namespace PackHauer\UnitAttribute\Test\Integration\Model\Attribute;

use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Helper\Product as ProductHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\AttributeFactory;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use PackHauer\UnitAttribute\Api\UnitAttributeMetadataInterface;
use PackHauer\UnitAttribute\Model\Attribute\Backend\Unit;

#[AppIsolation(true), DbIsolation(true)]
class UnitAttributeIntegrationTest extends TestCase
{
    private const string ATTRIBUTE_CODE = 'vendivo_unit_it';

    public function testCreatesDecimalUnitAttributeWithNamespacedMetadata(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $dataSetup = $objectManager->get(ModuleDataSetupInterface::class);
        $eavSetup = $objectManager->get(EavSetupFactory::class)->create(['setup' => $dataSetup]);
        $metadata = $objectManager->get(UnitAttributeMetadataInterface::class);

        $eavSetup->removeAttribute(Product::ENTITY, self::ATTRIBUTE_CODE);
        try {
            $eavSetup->addAttribute(Product::ENTITY, self::ATTRIBUTE_CODE, [
                'input' => 'unit',
                'label' => 'Integration Unit',
                'required' => false,
                'user_defined' => true,
                'additional_data' => $metadata->withUnit(null, 'CENTIMETER', 'cm'),
            ]);

            $attribute = $objectManager->get(ProductAttributeRepositoryInterface::class)
                ->get(self::ATTRIBUTE_CODE);
            self::assertSame('unit', $attribute->getFrontendInput());
            self::assertSame('decimal', $attribute->getBackendType());
            self::assertSame(Unit::class, $attribute->getBackendModel());
            self::assertSame(
                ['name' => 'CENTIMETER', 'symbol' => 'cm'],
                $metadata->get(self::ATTRIBUTE_CODE)
            );

            $attributeModel = $objectManager->get(AttributeFactory::class)->create();
            self::assertSame('decimal', $attributeModel->getBackendTypeByInput('unit'));
            self::assertSame(
                Unit::class,
                $objectManager->get(ProductHelper::class)->getAttributeBackendModelByInputType('unit')
            );
        } finally {
            $eavSetup->removeAttribute(Product::ENTITY, self::ATTRIBUTE_CODE);
        }
    }
}
