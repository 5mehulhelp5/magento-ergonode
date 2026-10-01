<?php

declare(strict_types=1);

namespace PackHauer\FileAttribute\Test\Integration\Setup;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PackHauer\FileAttribute\Model\Attribute\Backend\File;
use PackHauer\FileAttribute\Model\FileStorage;
use PackHauer\FileAttribute\Setup\Uninstall;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class UninstallIntegrationTest extends TestCase
{
    private const string ATTRIBUTE_CODE = 'packhauer_file_uninstall_it';
    private const string SHARED_ATTRIBUTE_CODE = 'packhauer_file_shared_it';
    private const string LEGACY_ATTRIBUTE_CODE = 'packhauer_file_legacy_it';
    private WriteInterface $mediaDirectory;

    protected function setUp(): void
    {
        $this->mediaDirectory = Bootstrap::getObjectManager()
            ->get(Filesystem::class)
            ->getDirectoryWrite(DirectoryList::MEDIA);
    }

    public function testStandardUninstallIsResolvableByMagento(): void
    {
        self::assertInstanceOf(
            UninstallInterface::class,
            Bootstrap::getObjectManager()->create(Uninstall::class)
        );
    }

    #[DataFixture(ProductFixture::class, ['sku' => 'packhauer-file-uninstall-%uniqid%'], as: 'product')]
    public function testRemovesDataAclAndOwnedFilesWhilePreservingSharedFile(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $dataSetup = $objectManager->get(ModuleDataSetupInterface::class);
        $eavSetup = $objectManager->get(EavSetupFactory::class)->create(['setup' => $dataSetup]);
        $resource = $objectManager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $ownedDirectory = FileStorage::PERMANENT_DIRECTORY . '/' . self::ATTRIBUTE_CODE;
        $removedPath = $ownedDirectory . '/removed.txt';
        $sharedPath = $ownedDirectory . '/shared.txt';
        $temporaryPath = FileStorage::TEMPORARY_DIRECTORY . '/uninstall/temporary.txt';

        foreach ([self::ATTRIBUTE_CODE, self::SHARED_ATTRIBUTE_CODE, self::LEGACY_ATTRIBUTE_CODE] as $code) {
            $eavSetup->removeAttribute(Product::ENTITY, $code);
        }
        $this->mediaDirectory->delete($ownedDirectory);
        $this->mediaDirectory->delete(FileStorage::TEMPORARY_DIRECTORY);

        try {
            $this->createAttributes($eavSetup);
            $product = DataFixtureStorageManager::getStorage()->get('product');
            $productId = (int)$product->getId();
            $productResource = $objectManager->get(ProductResource::class);
            $linkField = $productResource->getLinkField();
            $linkValue = (int)$connection->fetchOne(
                $connection->select()
                    ->from($resource->getTableName('catalog_product_entity'), [$linkField])
                    ->where($productResource->getIdFieldName() . ' = ?', $productId)
                    ->limit(1)
            );
            $attributeId = $this->getAttributeId($resource, self::ATTRIBUTE_CODE);
            $sharedAttributeId = $this->getAttributeId($resource, self::SHARED_ATTRIBUTE_CODE);
            $this->insertAttributeValue($resource, $linkField, $linkValue, $attributeId, $removedPath);
            $this->insertAttributeValue($resource, $linkField, $linkValue, $sharedAttributeId, $sharedPath);
            $this->insertAclRules($resource);
            $this->writeFile($removedPath);
            $this->writeFile($sharedPath);
            $this->writeFile($temporaryPath);

            $setup = $this->createStub(SchemaSetupInterface::class);
            $setup->method('getConnection')->willReturn($connection);
            $setup->method('getTable')->willReturnCallback(
                static fn(string $table): string => $resource->getTableName($table)
            );
            $objectManager->create(Uninstall::class)->uninstall(
                $setup,
                $this->createStub(ModuleContextInterface::class)
            );

            self::assertSame(0, $this->getAttributeId($resource, self::ATTRIBUTE_CODE));
            self::assertSame(0, $this->getAttributeId($resource, self::LEGACY_ATTRIBUTE_CODE));
            self::assertSame($sharedAttributeId, $this->getAttributeId($resource, self::SHARED_ATTRIBUTE_CODE));
            self::assertSame(0, $this->countAttributeValues($resource, $attributeId));
            self::assertSame(1, $this->countAttributeValues($resource, $sharedAttributeId));
            self::assertFalse($this->mediaDirectory->isExist($removedPath));
            self::assertTrue($this->mediaDirectory->isFile($sharedPath));
            self::assertFalse($this->mediaDirectory->isExist(FileStorage::TEMPORARY_DIRECTORY));
            self::assertSame(
                ['Other_Module::resource'],
                $connection->fetchCol(
                    $connection->select()
                        ->from($resource->getTableName('authorization_rule'), ['resource_id'])
                        ->where('resource_id IN (?)', [
                            'PackHauer_FileAttribute::upload',
                            'Vendivo_FileAttribute::upload',
                            'Other_Module::resource',
                        ])
                )
            );
        } finally {
            foreach ([self::ATTRIBUTE_CODE, self::SHARED_ATTRIBUTE_CODE, self::LEGACY_ATTRIBUTE_CODE] as $code) {
                $eavSetup->removeAttribute(Product::ENTITY, $code);
            }
            $connection->delete($resource->getTableName('authorization_rule'), [
                'resource_id IN (?)' => [
                    'PackHauer_FileAttribute::upload',
                    'Vendivo_FileAttribute::upload',
                    'Other_Module::resource',
                ],
            ]);
            $this->mediaDirectory->delete($ownedDirectory);
            $this->mediaDirectory->delete(FileStorage::TEMPORARY_DIRECTORY);
        }
    }

    private function createAttributes(EavSetup $eavSetup): void
    {
        $eavSetup->addAttribute(Product::ENTITY, self::ATTRIBUTE_CODE, [
            'type' => 'varchar',
            'input' => 'file',
            'label' => 'File uninstall integration',
            'required' => false,
            'user_defined' => true,
            'backend' => File::class,
        ]);
        $eavSetup->addAttribute(Product::ENTITY, self::SHARED_ATTRIBUTE_CODE, [
            'type' => 'varchar',
            'input' => 'text',
            'label' => 'Shared file reference integration',
            'required' => false,
            'user_defined' => true,
        ]);
        $eavSetup->addAttribute(Product::ENTITY, self::LEGACY_ATTRIBUTE_CODE, [
            'type' => 'varchar',
            'input' => 'text',
            'label' => 'Legacy file uninstall integration',
            'required' => false,
            'user_defined' => true,
            'backend' => $this->legacyBackendModel(),
        ]);
    }

    private function getAttributeId(ResourceConnection $resource, string $attributeCode): int
    {
        $connection = $resource->getConnection();
        $attributeId = $connection->fetchOne(
            $connection->select()
                ->from($resource->getTableName('eav_attribute'), ['attribute_id'])
                ->where('attribute_code = ?', $attributeCode)
                ->limit(1)
        );

        return $attributeId === false ? 0 : (int)$attributeId;
    }

    private function insertAttributeValue(
        ResourceConnection $resource,
        string $linkField,
        int $linkValue,
        int $attributeId,
        string $value
    ): void {
        $resource->getConnection()->insert($resource->getTableName('catalog_product_entity_varchar'), [
            'attribute_id' => $attributeId,
            'store_id' => 0,
            $linkField => $linkValue,
            'value' => $value,
        ]);
    }

    private function insertAclRules(ResourceConnection $resource): void
    {
        $connection = $resource->getConnection();
        $roleId = (int)$connection->fetchOne(
            $connection->select()
                ->from($resource->getTableName('authorization_role'), ['role_id'])
                ->limit(1)
        );
        foreach ([
            'PackHauer_FileAttribute::upload',
            'Vendivo_FileAttribute::upload',
            'Other_Module::resource',
        ] as $resourceId) {
            $connection->insert($resource->getTableName('authorization_rule'), [
                'role_id' => $roleId,
                'resource_id' => $resourceId,
                'privileges' => null,
                'permission' => 'allow',
            ]);
        }
    }

    private function writeFile(string $path): void
    {
        $directory = dirname($path);
        $this->mediaDirectory->create($directory);
        $this->mediaDirectory->writeFile($path, 'integration fixture');
    }

    private function countAttributeValues(ResourceConnection $resource, int $attributeId): int
    {
        $connection = $resource->getConnection();

        return (int)$connection->fetchOne(
            $connection->select()
                ->from($resource->getTableName('catalog_product_entity_varchar'), ['count' => 'COUNT(*)'])
                ->where('attribute_id = ?', $attributeId)
        );
    }

    private function legacyBackendModel(): string
    {
        return implode('\\', ['Vendivo', 'FileAttribute', 'Model', 'Attribute', 'Backend', 'File']);
    }
}
