<?php

declare(strict_types=1);

namespace Ergonode\Media\Setup\Uninstall;

use Ergonode\Media\Model\Config\MediaConfig;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class RuntimeRecordCleaner
{
    private const string QUEUE_NAME = 'ergonode.media.gallery';

    private const array DELETE_RULES = [
        ['cron_schedule', 'job_code IN (?)', [
            'ergonode_multimedia_stream_schedule', 'ergonode_media_recovery', 'ergonode_media_scan',
        ]],
        ['core_config_data', 'path IN (?)', [
            MediaConfig::XML_PATH_GALLERY_MODE,
            MediaConfig::XML_PATH_STREAM_PAGE_SIZE,
            MediaConfig::XML_PATH_BATCH_SIZE,
            MediaConfig::XML_PATH_MAXIMUM_ATTEMPTS,
            MediaConfig::XML_PATH_LEASE_SECONDS,
        ]],
    ];

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
        $this->removeQueueRecords($setup, $connection);
        foreach (self::DELETE_RULES as [$table, $condition, $value]) {
            $tableName = $setup->getTable($table);
            if ($connection->isTableExists($tableName)) {
                $connection->delete($tableName, [$condition => $value]);
            }
        }
        foreach (self::MODULE_TABLES as $table) {
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
