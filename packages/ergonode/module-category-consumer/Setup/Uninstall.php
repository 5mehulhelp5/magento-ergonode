<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Setup;

use Ergonode\CategoryConsumer\Model\Config\CategoryConfigProvider;
use Ergonode\CategoryConsumer\Model\Import\CategoryEntityStreamImporter;

use Ergonode\CategoryConsumer\Model\Import\CategoryTreeStreamImporter;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;

class Uninstall implements UninstallInterface
{
    private const array ACL_RESOURCES = [
        'Ergonode_CategoryConsumer::category_tree_sync',
    ];

    private const array PROCESS_CODES = [
        CategoryTreeStreamImporter::PROCESS_CODE,
        CategoryEntityStreamImporter::PROCESS_CODE,
        'category_tree_deleted_stream',
    ];

    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        unset($context);
        $connection = $setup->getConnection();
        $connection->startSetup();

        try {
            $deleteRules = [
                ['authorization_rule', 'resource_id IN (?)', self::ACL_RESOURCES],
                ['cron_schedule', 'job_code IN (?)', [
                    'ergonode_category_import', 'ergonode_category_attribute_import',
                ]],
                ['core_config_data', 'path IN (?)', [
                    CategoryConfigProvider::XML_PATH_CRON_ENABLED,
                    CategoryConfigProvider::XML_PATH_CRON_SCHEDULE,
                    CategoryConfigProvider::XML_PATH_DATA_ENABLED,
                    CategoryConfigProvider::XML_PATH_DATA_CRON_ENABLED,
                    CategoryConfigProvider::XML_PATH_DATA_CRON_SCHEDULE,
                    CategoryConfigProvider::XML_PATH_NAME_MODE,
                ]],
                ['ergonode_import_cursor', 'process_code IN (?)', self::PROCESS_CODES],
            ];
            foreach ($deleteRules as [$table, $condition, $value]) {
                $tableName = $setup->getTable($table);
                if ($connection->isTableExists($tableName)) {
                    $connection->delete($tableName, [$condition => $value]);
                }
            }
        } finally {
            $connection->endSetup();
        }
    }
}
