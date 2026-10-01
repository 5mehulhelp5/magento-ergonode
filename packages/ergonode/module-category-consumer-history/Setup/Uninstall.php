<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistory\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;

class Uninstall implements UninstallInterface
{
    private const string ACL_VIEW = 'Ergonode_CategoryConsumerHistory::view';

    private const array ACL_RESOURCES = [
        self::ACL_VIEW,
    ];

    private const array OWNED_TABLES = [
        'ergonode_category_history_change',
        'ergonode_category_history_change_set',
        'ergonode_category_history_operation',
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
