<?php

declare(strict_types=1);

namespace Ergonode\Media\Setup\Uninstall;

use Magento\Framework\Setup\SchemaSetupInterface;

class RuntimeRecordCleaner
{
    private const array MODULE_TABLES = [
        'ergonode_media_scan',
        'ergonode_media_local_file',
        'ergonode_media_state',
        'ergonode_media_file_usage',
        'ergonode_media_product_work',
        'ergonode_media_product_usage',
        'ergonode_media_materialization',
        'ergonode_media_asset',
    ];

    public function execute(SchemaSetupInterface $setup): void
    {
        $connection = $setup->getConnection();
        foreach (self::MODULE_TABLES as $table) {
            $tableName = $setup->getTable($table);
            if ($connection->isTableExists($tableName)) {
                $connection->dropTable($tableName);
            }
        }
    }
}
