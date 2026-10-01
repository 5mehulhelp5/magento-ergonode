<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Setup;

use Ergonode\ProductAttributeConsumer\Model\Config\ProductAttributeConfigProvider;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;

class Uninstall implements UninstallInterface
{
    private const string ATTRIBUTE_SYNC_RESOURCE = 'Ergonode_ProductAttributeConsumer::attribute_sync';
    private const string OPTION_SYNC_RESOURCE = 'Ergonode_ProductAttributeConsumer::option_sync';

    private const array LEGACY_CACHE_COLUMNS = [
        'magento_option_id',
        'sync_status',
        'sync_message',
    ];

    private const array CONFIGURATION_PATHS = [
        ProductAttributeConfigProvider::XML_PATH_MAP_IDENTICAL_CODES,
        ProductAttributeConfigProvider::XML_PATH_SYNCHRONIZE_OPTION_SORT_ORDER,
        ProductAttributeConfigProvider::XML_PATH_DELETE_MISSING_MAGENTO_OPTIONS,
        ProductAttributeConfigProvider::XML_PATH_IMPORT_CRON_ENABLED,
        ProductAttributeConfigProvider::XML_PATH_IMPORT_CRON_SCHEDULE,
    ];

    private const array DELETE_RULES = [
        ['core_config_data', 'path IN (?)', self::CONFIGURATION_PATHS],
        ['cron_schedule', 'job_code = ?', 'ergonode_attribute_import'],
        ['ergonode_import_cursor', 'process_code = ?', 'attributeStream'],
        ['authorization_rule', 'resource_id IN (?)', [
            self::ATTRIBUTE_SYNC_RESOURCE,
            self::OPTION_SYNC_RESOURCE,
        ]],
    ];

    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        unset($context);
        $connection = $setup->getConnection();
        $connection->startSetup();

        try {
            foreach (self::DELETE_RULES as [$table, $condition, $value]) {
                $tableName = $setup->getTable($table);
                if ($connection->isTableExists($tableName)) {
                    $connection->delete($tableName, [$condition => $value]);
                }
            }

            $optionCacheTable = $setup->getTable('ergonode_attribute_option');
            if ($connection->isTableExists($optionCacheTable)) {
                foreach (self::LEGACY_CACHE_COLUMNS as $column) {
                    if ($connection->tableColumnExists($optionCacheTable, $column)) {
                        $connection->dropColumn($optionCacheTable, $column);
                    }
                }
            }
        } finally {
            $connection->endSetup();
        }
    }
}
