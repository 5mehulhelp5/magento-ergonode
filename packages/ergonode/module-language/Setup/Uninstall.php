<?php

declare(strict_types=1);

namespace Ergonode\Language\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;

class Uninstall implements UninstallInterface
{
    private const string ACL_LANGUAGE_MAPPING = 'Ergonode_Language::language_mapping';
    private const string ACL_LANGUAGE_MAPPING_REFRESH = 'Ergonode_Language::language_mapping_refresh';
    private const string ACL_LANGUAGE_MAPPING_SAVE = 'Ergonode_Language::language_mapping_save';

    private const array ACL_RESOURCES = [
        self::ACL_LANGUAGE_MAPPING,
        self::ACL_LANGUAGE_MAPPING_REFRESH,
        self::ACL_LANGUAGE_MAPPING_SAVE,
    ];

    private const array OWNED_TABLES = [
        'ergonode_language_store_mapping',
        'ergonode_language',
    ];

    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        unset($context);
        $connection = $setup->getConnection();
        $connection->startSetup();

        try {
            foreach ([
                'authorization_rule' => ['resource_id IN (?)' => self::ACL_RESOURCES],
                'ergonode_mapping_visibility' => ['entity_type = ?' => 'language'],
            ] as $table => $condition) {
                $tableName = $setup->getTable($table);
                if ($connection->isTableExists($tableName)) {
                    $connection->delete($tableName, $condition);
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
