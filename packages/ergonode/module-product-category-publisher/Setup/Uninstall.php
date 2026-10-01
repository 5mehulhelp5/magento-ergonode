<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryPublisher\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;

class Uninstall implements UninstallInterface
{
    private const string CONFIG_PATH = 'ergonode_products/publication/category_mode';

    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        unset($context);
        $connection = $setup->getConnection();
        $connection->startSetup();

        try {
            $configTable = $setup->getTable('core_config_data');
            if ($connection->isTableExists($configTable)) {
                $connection->delete($configTable, ['path = ?' => self::CONFIG_PATH]);
            }
        } finally {
            $connection->endSetup();
        }
    }
}
