<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeHistory\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;

class Uninstall implements UninstallInterface
{
    private const string HISTORY_RESOURCE = 'Ergonode_ProductAttributeHistory::view';

    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        unset($context);
        $connection = $setup->getConnection();
        $connection->startSetup();
        try {
            $rules = $setup->getTable('authorization_rule');
            if ($connection->isTableExists($rules)) {
                $connection->delete($rules, ['resource_id = ?' => self::HISTORY_RESOURCE]);
            }
            $table = $setup->getTable('ergonode_product_attribute_history_operation');
            if ($connection->isTableExists($table)) {
                $connection->dropTable($table);
            }
        } finally {
            $connection->endSetup();
        }
    }
}
