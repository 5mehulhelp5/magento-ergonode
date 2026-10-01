<?php

declare(strict_types=1);

namespace Ergonode\Consumer\Setup;

use Ergonode\Consumer\Model\Config\ConnectionMode;
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
                    'path IN (?)' => [
                        ConnectionMode::XML_PATH_TEST_API_KEY,
                        ConnectionMode::XML_PATH_PRODUCTION_API_KEY,
                    ],
                ]);
            }
        } finally {
            $connection->endSetup();
        }
    }
}
