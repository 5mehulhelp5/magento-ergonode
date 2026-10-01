<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Setup\Uninstall;

use Magento\Framework\Setup\SchemaSetupInterface;

class RuntimeRecordCleaner
{
    private const array CONFIG_PATHS = [
        'ergonode_templates/import/sync_attributes',
        'ergonode_templates/import/sync_sections',
    ];

    private const array OWNED_TABLES = [
        'ergonode_template_manual_placement',
        'ergonode_template_attribute_ownership',
        'ergonode_template_group_ownership',
    ];

    public function execute(SchemaSetupInterface $setup): void
    {
        $connection = $setup->getConnection();
        $configurationTable = $setup->getTable('core_config_data');
        if ($connection->isTableExists($configurationTable)) {
            $connection->delete($configurationTable, ['path IN (?)' => self::CONFIG_PATHS]);
        }

        foreach (self::OWNED_TABLES as $table) {
            $tableName = $setup->getTable($table);
            if ($connection->isTableExists($tableName)) {
                $connection->dropTable($tableName);
            }
        }
    }
}
