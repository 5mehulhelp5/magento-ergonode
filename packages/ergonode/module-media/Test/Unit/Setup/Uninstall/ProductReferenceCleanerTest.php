<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Unit\Setup\Uninstall;

use Ergonode\Media\Setup\Uninstall\ProductReferenceCleaner;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Eav\Model\Config;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Setup\SchemaSetupInterface;
use PHPUnit\Framework\TestCase;

class ProductReferenceCleanerTest extends TestCase
{
    public function testClearsOnlyStillManagedFileAttributeValue(): void
    {
        [$setup, $connection] = $this->setupMocks();
        $connection->method('isTableExists')->willReturnCallback(
            static fn(string $table): bool => $table !== 'prefix_ergonode_media_product_usage'
        );
        $select = $this->select();
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturn([[
            'product_id' => 23,
            'attribute_code' => 'manual',
            'store_id' => 2,
            'attached_path' => 'catalog/product/ergonode/shared/manual.pdf',
        ]]);
        $attribute = $this->createStub(AbstractAttribute::class);
        $attribute->method('getAttributeId')->willReturn(91);
        $attribute->method('getBackendTable')->willReturn('catalog_product_entity_varchar');
        $attribute->method('getBackendType')->willReturn('varchar');
        $attribute->method('getData')->willReturn(0);
        $eav = $this->createStub(Config::class);
        $eav->method('getAttribute')->willReturn($attribute);
        $products = $this->createStub(ProductResource::class);
        $products->method('getLinkField')->willReturn('entity_id');
        $products->method('getIdFieldName')->willReturn('entity_id');
        $connection->expects(self::once())->method('delete')->with(
            'prefix_catalog_product_entity_varchar',
            [
                'attribute_id = ?' => 91,
                'entity_id = ?' => 23,
                'store_id = ?' => 2,
                'value = ?' => 'catalog/product/ergonode/shared/manual.pdf',
            ]
        );

        (new ProductReferenceCleaner($eav, $products))->execute($setup);
    }

    public function testRemovesManagedGalleryLinkAndOrphanedValue(): void
    {
        [$setup, $connection] = $this->setupMocks();
        $connection->method('isTableExists')->willReturnCallback(
            static fn(string $table): bool => $table !== 'prefix_ergonode_media_file_usage'
        );
        $select = $this->select();
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturn([[
            'product_id' => 23,
            'attached_path' => 'catalog/product/ergonode/seo/a/b/photo.jpg',
        ]]);
        $connection->method('fetchCol')->willReturn([71]);
        $connection->method('fetchOne')->willReturn(false);
        $attribute = $this->createStub(AbstractAttribute::class);
        $attribute->method('getAttributeId')->willReturn(90);
        $eav = $this->createStub(Config::class);
        $eav->method('getAttribute')->willReturn($attribute);
        $deletes = [
            ['prefix_catalog_product_entity_media_gallery_value_to_entity', [
                'value_id = ?' => 71,
                'entity_id = ?' => 23,
            ]],
            ['prefix_catalog_product_entity_media_gallery_value', [
                'value_id = ?' => 71,
                'entity_id = ?' => 23,
            ]],
            ['prefix_catalog_product_entity_media_gallery_value', ['value_id = ?' => 71]],
            ['prefix_catalog_product_entity_media_gallery', ['value_id = ?' => 71]],
        ];
        $connection->expects(self::exactly(count($deletes)))
            ->method('delete')
            ->willReturnCallback(static function (string $table, array $where) use (&$deletes): int {
                self::assertSame(array_shift($deletes), [$table, $where]);

                return 1;
            });

        (new ProductReferenceCleaner($eav, $this->createStub(ProductResource::class)))->execute($setup);

        self::assertSame([], $deletes);
    }

    /** @return array{SchemaSetupInterface, AdapterInterface} */
    private function setupMocks(): array
    {
        $setup = $this->createStub(SchemaSetupInterface::class);
        $connection = $this->createMock(AdapterInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(static fn(string $table): string => 'prefix_' . $table);

        return [$setup, $connection];
    }

    private function select(): Select
    {
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('limit')->willReturnSelf();

        return $select;
    }
}
