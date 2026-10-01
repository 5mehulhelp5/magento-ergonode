<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttribute\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;

class Uninstall implements UninstallInterface
{
    private const array ACL_RESOURCES = [
        'Ergonode_CategoryConsumer::category_attribute_mapping',
        'Ergonode_CategoryConsumer::category_attribute_refresh',
        'Ergonode_CategoryConsumer::category_attribute_save',
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
            $visibilityTable = $setup->getTable('ergonode_mapping_visibility');
            if ($connection->isTableExists($visibilityTable)) {
                $connection->delete($visibilityTable, [
                    'entity_type IN (?)' => ['category_attribute', 'category_option'],
                ]);
            }
            foreach (['ergonode_category_option_mapping', 'ergonode_category_attribute_mapping'] as $table) {
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
