<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Setup;

use Ergonode\CategoryAttributeConsumer\Model\Config\CategoryNameTargetProvider;
use Ergonode\CategoryAttributeConsumer\Model\Config\CategoryAttributeConfigProvider;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;

class Uninstall implements UninstallInterface
{
    private const array CONFIG_PATHS = [
        CategoryNameTargetProvider::XML_PATH_ATTRIBUTE,
        CategoryAttributeConfigProvider::XML_PATH_NAME_MODE,
        CategoryAttributeConfigProvider::XML_PATH_INCLUDE_IN_MENU_MODE,
        CategoryAttributeConfigProvider::XML_PATH_INCLUDE_IN_MENU_DEFAULT,
        CategoryAttributeConfigProvider::XML_PATH_IS_ACTIVE_MODE,
        CategoryAttributeConfigProvider::XML_PATH_IS_ACTIVE_DEFAULT,
        CategoryAttributeConfigProvider::LEGACY_XML_PATH_SYNCHRONIZATION_ENABLED,
        CategoryAttributeConfigProvider::LEGACY_XML_PATH_NAME_MODE,
        CategoryAttributeConfigProvider::LEGACY_XML_PATH_INCLUDE_IN_MENU_MODE,
        CategoryAttributeConfigProvider::LEGACY_XML_PATH_INCLUDE_IN_MENU_DEFAULT,
        CategoryAttributeConfigProvider::LEGACY_XML_PATH_IS_ACTIVE_MODE,
        CategoryAttributeConfigProvider::LEGACY_XML_PATH_IS_ACTIVE_DEFAULT,
    ];

    private const array OWNED_TABLES = [
        'ergonode_category_entity_snapshot',
        'ergonode_category_attribute',
    ];

    private const array PROCESS_CODES = [
        'category_deleted_stream',
    ];

    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        unset($context);
        $connection = $setup->getConnection();
        $connection->startSetup();

        try {
            $deleteRules = [
                ['core_config_data', 'path IN (?)', self::CONFIG_PATHS],
                ['ergonode_import_cursor', 'process_code IN (?)', self::PROCESS_CODES],
            ];
            foreach ($deleteRules as [$table, $condition, $value]) {
                $tableName = $setup->getTable($table);
                if ($connection->isTableExists($tableName)) {
                    $connection->delete($tableName, [$condition => $value]);
                }
            }

            foreach (self::OWNED_TABLES as $table) {
                $tableName = $setup->getTable($table);
                if ($connection->isTableExists($tableName)) {
                    $connection->dropTable($tableName);
                }
            }
        } finally {
            $connection->endSetup();
        }
    }
}
