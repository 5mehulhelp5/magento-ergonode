<?php

declare(strict_types=1);

namespace Ergonode\Product\Setup;

use Ergonode\Product\Model\Config\ProductIdentityModeProvider;
use Ergonode\Product\Model\ResourceModel\MagentoIdentityAttribute;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;

class Uninstall implements UninstallInterface
{
    private const string MAPPING_TABLE = 'ergonode_product_mapping';

    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        unset($context);
        $connection = $setup->getConnection();
        $connection->startSetup();

        try {
            $configurationTable = $setup->getTable('core_config_data');
            if ($connection->isTableExists($configurationTable)) {
                $connection->delete(
                    $configurationTable,
                    ['path = ?' => ProductIdentityModeProvider::XML_PATH_SKU_MODE]
                );
                $connection->delete(
                    $configurationTable,
                    ['path = ?' => MagentoIdentityAttribute::XML_PATH_ATTRIBUTE]
                );
            }
            $mappingTable = $setup->getTable(self::MAPPING_TABLE);
            if ($connection->isTableExists($mappingTable)) {
                $connection->dropTable($mappingTable);
            }
        } finally {
            $connection->endSetup();
        }
    }
}
