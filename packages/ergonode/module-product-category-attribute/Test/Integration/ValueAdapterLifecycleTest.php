<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryAttribute\Test\Integration;

use Ergonode\ProductAttribute\Model\Mapping\AttributeMappingNormalizer;
use Ergonode\ProductAttribute\Model\Mapping\AttributeMappingSaver;
use Ergonode\ProductAttribute\Model\Mapping\ValueAdapterRegistry;
use Ergonode\ProductAttribute\Model\ResourceModel\CompleteMappingProvider;
use Ergonode\ProductCategoryAttribute\Api\CategoryReferenceAttributeConfigInterface;
use Ergonode\ProductCategoryAttribute\Model\CategoryReferenceValueAdapter;
use Ergonode\ProductCategoryAttribute\Model\Config\CategoryReferenceAttributeConfig;
use Ergonode\ProductCategoryAttribute\Setup\Uninstall;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Config\FileResolverInterface;
use Magento\Framework\ObjectManager\Config\Reader\Dom;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class ValueAdapterLifecycleTest extends TestCase
{
    public function testRemovingAndRestoringDirectionsPreservesSavedRequirement(): void
    {
        $objects = Bootstrap::getObjectManager();
        $resource = $objects->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $table = $resource->getTableName('ergonode_product_attribute_mapping');
        $connection->delete($table);
        $normalizer = $objects->create(AttributeMappingNormalizer::class, [
            'valueAdapters' => $this->registry(true, true),
        ]);
        $saver = $objects->create(AttributeMappingSaver::class, ['normalizer' => $normalizer]);
        $mapping = [
            'left' => ['code' => 'remote_reference', 'type' => 'text'],
            'right' => ['code' => 'reference', 'type' => 'text'],
        ];
        $saver->save([$mapping], []);
        $original = $connection->fetchRow($connection->select()->from($table));
        self::assertSame('category_reference', $original['value_adapter']);

        foreach ([[true, true], [false, true], [true, false], [false, false], [true, true]] as [$import, $publish]) {
            $provider = $objects->create(CompleteMappingProvider::class, [
                'valueAdapters' => $this->registry($import, $publish),
            ]);
            self::assertCount($import ? 1 : 0, $provider->getMappings('import'));
            self::assertCount($publish ? 1 : 0, $provider->getMappings('publish'));
            self::assertSame($original, $connection->fetchRow($connection->select()->from($table)));
        }

        // The neutral owner must preserve the marker even after the base module disappears.
        $missing = new ValueAdapterRegistry();
        $normalizer = $objects->create(AttributeMappingNormalizer::class, ['valueAdapters' => $missing]);
        $saver = $objects->create(AttributeMappingSaver::class, ['normalizer' => $normalizer]);
        $saver->save([$mapping], []);
        $provider = $objects->create(CompleteMappingProvider::class, ['valueAdapters' => $missing]);
        self::assertSame([], $provider->getMappings('import'));
        self::assertSame([], $provider->getMappings('publish'));
        self::assertSame($original, $connection->fetchRow($connection->select()->from($table)));
    }

    public function testUninstallDeletesOwnConfigurationAndRetainsNeutralMapping(): void
    {
        $objects = Bootstrap::getObjectManager();
        $resource = $objects->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $mappingTable = $resource->getTableName('ergonode_product_attribute_mapping');
        $configTable = $resource->getTableName('core_config_data');
        $connection->insert($mappingTable, [
            'ergonode_attribute_code' => 'uninstall_reference',
            'magento_attribute_code' => 'uninstall_reference',
            'value_adapter' => 'category_reference',
            'content_hash' => hash('sha256', 'uninstall_reference'),
        ]);
        $connection->insertOnDuplicate($configTable, [
            'scope' => 'default', 'scope_id' => 0,
            'path' => CategoryReferenceAttributeConfig::XML_PATH_ATTRIBUTE, 'value' => 'uninstall_reference',
        ], ['value']);
        $setup = $this->createStub(SchemaSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback($resource->getTableName(...));
        (new Uninstall())->uninstall($setup, $this->createStub(ModuleContextInterface::class));
        self::assertFalse($connection->fetchOne(
            $connection->select()->from($configTable, 'value')
                ->where('path = ?', CategoryReferenceAttributeConfig::XML_PATH_ATTRIBUTE)
        ));
        self::assertSame('category_reference', $connection->fetchOne(
            $connection->select()->from($mappingTable, 'value_adapter')
                ->where('magento_attribute_code = ?', 'uninstall_reference')
        ));
    }

    private function registry(bool $import, bool $publish): ValueAdapterRegistry
    {
        $objects = Bootstrap::getObjectManager();
        $registrar = $objects->get(ComponentRegistrar::class);
        $modules = ['Ergonode_ProductCategoryAttribute'];
        if ($import) {
            $modules[] = 'Ergonode_ProductCategoryAttributeConsumer';
        }
        if ($publish) {
            $modules[] = 'Ergonode_ProductCategoryAttributePublisher';
        }
        $files = [];
        foreach ($modules as $module) {
            $modulePath = $registrar->getPath(ComponentRegistrar::MODULE, $module);
            self::assertNotNull($modulePath, $module . ' must be registered');
            $files[] = $modulePath . '/etc/di.xml';
        }
        $resolver = $this->createStub(FileResolverInterface::class);
        $resolver->method('get')->willReturn(array_map(file_get_contents(...), $files));
        $reader = $objects->create(Dom::class, ['fileResolver' => $resolver]);
        $configuration = $reader->read('global');
        $definition = $configuration[ValueAdapterRegistry::class]['arguments']['adapters']['category_reference'];
        self::assertSame(CategoryReferenceValueAdapter::class, $definition['instance']);
        $config = $this->createStub(CategoryReferenceAttributeConfigInterface::class);
        $config->method('isConfigured')->willReturnCallback(static fn (string $code): bool => $code === 'reference');
        $arguments = $configuration[CategoryReferenceValueAdapter::class]['arguments'] ?? [];
        $arguments['config'] = $config;

        // Only the selected XML files may enable directions; do not merge the installed modules' DI defaults.
        return new ValueAdapterRegistry([
            'category_reference' => new CategoryReferenceValueAdapter(...$arguments),
        ]);
    }
}
