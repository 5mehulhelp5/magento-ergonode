<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Setup\Uninstall;

use Ergonode\ProductConsumer\Model\Import\ProductStreamScheduler;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class RuntimeRecordCleaner
{
    private const string QUEUE_NAME = 'ergonode.product.import';

    private const array DELETE_RULES = [
        ['cron_schedule', 'job_code IN (?)', [
            'ergonode_product_import_schedule',
            'ergonode_product_import_recovery',
        ]],
        ['core_config_data', 'path LIKE ?', 'ergonode_products/import/%'],
        ['ergonode_import_cursor', 'process_code IN (?)', [
            ProductStreamScheduler::CHANGED_PROCESS_CODE,
            ProductStreamScheduler::DELETED_PROCESS_CODE,
        ]],
    ];

    private const string WORK_TABLE = 'ergonode_product_import_item';

    public function execute(SchemaSetupInterface $setup): void
    {
        $connection = $setup->getConnection();
        $this->removeQueueRecords($setup, $connection);
        foreach (self::DELETE_RULES as [$table, $condition, $value]) {
            $tableName = $setup->getTable($table);
            if ($connection->isTableExists($tableName)) {
                $connection->delete($tableName, [$condition => $value]);
            }
        }

        $workTable = $setup->getTable(self::WORK_TABLE);
        if ($connection->isTableExists($workTable)) {
            $connection->dropTable($workTable);
        }
    }

    private function removeQueueRecords(SchemaSetupInterface $setup, AdapterInterface $connection): void
    {
        $messageTable = $setup->getTable('queue_message');
        $queueTable = $setup->getTable('queue');
        $statusTable = $setup->getTable('queue_message_status');
        $messageTableExists = $connection->isTableExists($messageTable);
        $queueTableExists = $connection->isTableExists($queueTable);
        $messageIds = $messageTableExists
            ? $connection->fetchCol(
                $connection->select()->from($messageTable, ['id'])->where('topic_name = ?', self::QUEUE_NAME)
            )
            : [];
        $queueIds = $queueTableExists
            ? $connection->fetchCol(
                $connection->select()->from($queueTable, ['id'])->where('name = ?', self::QUEUE_NAME)
            )
            : [];
        if ($connection->isTableExists($statusTable)) {
            if ($messageIds !== []) {
                $connection->delete($statusTable, ['message_id IN (?)' => $messageIds]);
            }
            if ($queueIds !== []) {
                $connection->delete($statusTable, ['queue_id IN (?)' => $queueIds]);
            }
        }
        if ($messageTableExists) {
            $connection->delete($messageTable, ['topic_name = ?' => self::QUEUE_NAME]);
        }
        if ($queueTableExists) {
            $connection->delete($queueTable, ['name = ?' => self::QUEUE_NAME]);
        }
    }
}
