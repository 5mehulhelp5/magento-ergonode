<?php

declare(strict_types=1);

namespace PackHauer\FileAttribute\Test\Integration\Model\Attribute;

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
use PackHauer\FileAttribute\Model\Attribute\Backend\File;

#[AppIsolation(true), DbIsolation(true)]
class FileAttributeIntegrationTest extends TestCase
{
    private const string ATTRIBUTE_CODE = 'vendivo_file_it';

    public function testCreatesVarcharFileAttributeWithModuleBackend(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $dataSetup = $objectManager->get(ModuleDataSetupInterface::class);
        $eavSetup = $objectManager->get(EavSetupFactory::class)->create(['setup' => $dataSetup]);

        $eavSetup->removeAttribute(Product::ENTITY, self::ATTRIBUTE_CODE);
        try {
            $eavSetup->addAttribute(Product::ENTITY, self::ATTRIBUTE_CODE, [
                'input' => 'file',
                'label' => 'Integration File',
                'required' => false,
                'user_defined' => true,
            ]);

            $attribute = $objectManager->get(ProductAttributeRepositoryInterface::class)
                ->get(self::ATTRIBUTE_CODE);
            self::assertSame('file', $attribute->getFrontendInput());
            self::assertSame('varchar', $attribute->getBackendType());
            self::assertSame(File::class, $attribute->getBackendModel());

            $attributeModel = $objectManager->get(AttributeFactory::class)->create();
            self::assertSame('varchar', $attributeModel->getBackendTypeByInput('file'));
            self::assertSame(
                File::class,
                $objectManager->get(ProductHelper::class)->getAttributeBackendModelByInputType('file')
            );
        } finally {
            $eavSetup->removeAttribute(Product::ENTITY, self::ATTRIBUTE_CODE);
        }
    }
}
