<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryAttribute\Setup;

use Ergonode\ProductCategoryAttribute\Model\Config\CategoryReferenceAttributeConfig;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;

class Uninstall implements UninstallInterface
{
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        unset($context);
        $connection = $setup->getConnection();
        $connection->startSetup();

        try {
            $configTable = $setup->getTable('core_config_data');
            if ($connection->isTableExists($configTable)) {
                $connection->delete($configTable, [
                    'path = ?' => CategoryReferenceAttributeConfig::XML_PATH_ATTRIBUTE,
                ]);
            }
        } finally {
            $connection->endSetup();
        }
    }
}
