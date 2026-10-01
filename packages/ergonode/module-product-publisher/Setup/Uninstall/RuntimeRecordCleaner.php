<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Setup\Uninstall;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class RuntimeRecordCleaner
{
    private const string QUEUE_NAME = 'ergonode.product.publish';

    private const string CRON_JOB = 'ergonode_product_publication_recovery';

    private const array OWNED_TABLES = [
        'ergonode_product_publisher_item',
        'ergonode_product_publisher_job',
    ];

    public function execute(SchemaSetupInterface $setup): void
    {
        $connection = $setup->getConnection();
        $this->removeQueueRecords($setup, $connection);
        $cronTable = $setup->getTable('cron_schedule');
        if ($connection->isTableExists($cronTable)) {
            $connection->delete($cronTable, ['job_code = ?' => self::CRON_JOB]);
        }
        foreach (self::OWNED_TABLES as $table) {
            $tableName = $setup->getTable($table);
            if ($connection->isTableExists($tableName)) {
                $connection->dropTable($tableName);
            }
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
