<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Setup\Uninstall;

use Ergonode\TemplateConsumer\Model\Import\TemplateListSynchronizer;
use Magento\Framework\Setup\SchemaSetupInterface;

class RuntimeRecordCleaner
{
    private const array DELETE_RULES = [
        ['cron_schedule', 'job_code = ?', 'ergonode_template_synchronize'],
        ['core_config_data', 'path LIKE ?', 'ergonode_templates/%'],
        ['ergonode_import_cursor', 'process_code IN (?)', [
            TemplateListSynchronizer::PROCESS_CODE,
            'templateList',
        ]],
        ['ergonode_mapping_visibility', 'entity_type = ?', 'template'],
    ];

    public function execute(SchemaSetupInterface $setup): void
    {
        $connection = $setup->getConnection();
        foreach (self::DELETE_RULES as [$table, $condition, $value]) {
            $tableName = $setup->getTable($table);
            if ($connection->isTableExists($tableName)) {
                $connection->delete($tableName, [$condition => $value]);
            }
        }
    }
}
