<?php

declare(strict_types=1);

namespace Ergonode\Core\Setup;

use Ergonode\Core\Model\Config\ConfigProvider;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;

class Uninstall implements UninstallInterface
{
    private const string ACL_MAIN = 'Ergonode_Core::main';
    private const string ACL_READINESS = 'Ergonode_Core::readiness';
    private const string ACL_SYNCHRONIZATIONS = 'Ergonode_Core::synchronizations';
    private const string ACL_SYNCHRONIZATIONS_RUN = 'Ergonode_Core::synchronizations_run';
    private const string ACL_SYNCHRONIZATIONS_RESET = 'Ergonode_Core::synchronizations_reset';
    private const string ACL_CONFIG = 'Ergonode_Core::config';

    private const array ACL_RESOURCES = [
        self::ACL_MAIN,
        self::ACL_READINESS,
        self::ACL_SYNCHRONIZATIONS,
        self::ACL_SYNCHRONIZATIONS_RUN,
        self::ACL_SYNCHRONIZATIONS_RESET,
        self::ACL_CONFIG,
    ];

    private const array OWNED_TABLES = [
        'ergonode_mapping_visibility',
        'ergonode_import_cursor',
    ];

    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        unset($context);
        $connection = $setup->getConnection();
        $connection->startSetup();

        try {
            $configurationTable = $setup->getTable('core_config_data');
            if ($connection->isTableExists($configurationTable)) {
                $connection->delete($configurationTable, [
                    'path IN (?)' => [
                        ConfigProvider::XML_PATH_GRAPHQL_URL,
                        ConfigProvider::XML_PATH_ENVIRONMENT,
                        ConfigProvider::XML_PATH_PRODUCTION_GRAPHQL_URL,
                        ConfigProvider::XML_PATH_MODE,
                        ConfigProvider::XML_PATH_ENABLED,
                        ConfigProvider::XML_PATH_REQUESTS_PER_MINUTE,
                        ConfigProvider::XML_PATH_PRODUCTION_REQUESTS_PER_MINUTE,
                    ],
                ]);
            }

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
