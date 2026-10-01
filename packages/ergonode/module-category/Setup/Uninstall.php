<?php

declare(strict_types=1);

namespace Ergonode\Category\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;

class Uninstall implements UninstallInterface
{
    private const array ACL_RESOURCES = [
        'Ergonode_CategoryConsumer::category_tree_manage',
        'Ergonode_CategoryConsumer::category_tree_refresh',
        'Ergonode_CategoryConsumer::category_tree_save',
        'Ergonode_CategoryConsumer::category_tree_mapping',
        'Ergonode_CategoryConsumer::category_tree_mapping_refresh',
        'Ergonode_CategoryConsumer::category_tree_mapping_auto_map',
        'Ergonode_CategoryConsumer::category_tree_mapping_save',
    ];

    private const array OWNED_TABLES = [
        'ergonode_category_mapping',
        'ergonode_category_snapshot',
        'ergonode_category_tree',
        'ergonode_category_tree_option',
    ];

    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        unset($context);
        $connection = $setup->getConnection();
        $connection->startSetup();

        try {
            $rules = $setup->getTable('authorization_rule');
            if ($connection->isTableExists($rules)) {
                $connection->delete($rules, ['resource_id IN (?)' => self::ACL_RESOURCES]);
            }
            $visibility = $setup->getTable('ergonode_mapping_visibility');
            if ($connection->isTableExists($visibility)) {
                $connection->delete($visibility, ['entity_type = ?' => 'category']);
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
