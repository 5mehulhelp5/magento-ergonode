<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;

class Uninstall implements UninstallInterface
{
    private const string ACL_ATTRIBUTE_REFRESH = 'Ergonode_AttributeConsumer::attribute_refresh';
    private const string ACL_OPTION_REFRESH = 'Ergonode_AttributeConsumer::option_refresh';

    private const array ACL_RESOURCES = [
        self::ACL_ATTRIBUTE_REFRESH,
        self::ACL_OPTION_REFRESH,
    ];

    private const array OWNED_TABLES = [
        'ergonode_attribute_option',
        'ergonode_attribute',
    ];

    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        unset($context);
        $connection = $setup->getConnection();
        $connection->startSetup();

        try {
            $authorizationRuleTable = $setup->getTable('authorization_rule');
            if ($connection->isTableExists($authorizationRuleTable)) {
                $connection->delete($authorizationRuleTable, ['resource_id IN (?)' => self::ACL_RESOURCES]);
            }

            $flagTable = $setup->getTable('flag');
            if ($connection->isTableExists($flagTable)) {
                $connection->delete($flagTable, ['flag_code = ?' => 'ergonode_attribute_definition_check']);
            }
            $cursorTable = $setup->getTable('ergonode_import_cursor');
            if ($connection->isTableExists($cursorTable)) {
                $connection->delete($cursorTable, ['process_code = ?' => 'attribute_definition_snapshot']);
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
