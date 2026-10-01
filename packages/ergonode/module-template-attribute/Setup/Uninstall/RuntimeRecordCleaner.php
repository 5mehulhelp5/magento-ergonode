<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttribute\Setup\Uninstall;

use Magento\Framework\Setup\SchemaSetupInterface;

class RuntimeRecordCleaner
{
    private const array OWNED_TABLES = [
        'ergonode_template_attribute',
        'ergonode_template_section',
    ];

    public function execute(SchemaSetupInterface $setup): void
    {
        $connection = $setup->getConnection();
        foreach (self::OWNED_TABLES as $table) {
            $tableName = $setup->getTable($table);
            if ($connection->isTableExists($tableName)) {
                $connection->dropTable($tableName);
            }
        }
    }
}
