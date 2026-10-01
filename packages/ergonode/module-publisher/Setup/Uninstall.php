<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Setup;

use Ergonode\Publisher\Model\Config\ConnectionMode;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;

class Uninstall implements UninstallInterface
{
    private const string ACL_RESOURCE = 'Ergonode_Publisher::rest_connection';

    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        unset($context);
        $connection = $setup->getConnection();
        $connection->startSetup();

        try {
            $tokenTable = $setup->getTable('ergonode_publisher_rest_connection');
            if ($connection->isTableExists($tokenTable)) {
                $connection->dropTable($tokenTable);
            }
            $ruleTable = $setup->getTable('authorization_rule');
            if ($connection->isTableExists($ruleTable)) {
                $connection->delete($ruleTable, ['resource_id = ?' => self::ACL_RESOURCE]);
            }
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
