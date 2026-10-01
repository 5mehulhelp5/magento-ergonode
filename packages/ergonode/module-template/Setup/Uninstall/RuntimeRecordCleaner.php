<?php

declare(strict_types=1);

namespace Ergonode\Template\Setup\Uninstall;

use Magento\Framework\Setup\SchemaSetupInterface;

class RuntimeRecordCleaner
{
    private const string ACL_MAPPING = 'Ergonode_TemplateConsumer::template_mapping';
    private const string ACL_REFRESH = 'Ergonode_TemplateConsumer::template_refresh';
    private const string ACL_SAVE = 'Ergonode_TemplateConsumer::template_save';
    private const string ACL_SYNC = 'Ergonode_TemplateConsumer::template_sync';

    private const array ACL_RESOURCES = [
        self::ACL_MAPPING,
        self::ACL_REFRESH,
        self::ACL_SAVE,
        self::ACL_SYNC,
    ];

    private const string TEMPLATE_TABLE = 'ergonode_template';

    public function execute(SchemaSetupInterface $setup): void
    {
        $connection = $setup->getConnection();
        $authorizationRuleTable = $setup->getTable('authorization_rule');
        if ($connection->isTableExists($authorizationRuleTable)) {
            $connection->delete($authorizationRuleTable, ['resource_id IN (?)' => self::ACL_RESOURCES]);
        }

        $templateTable = $setup->getTable(self::TEMPLATE_TABLE);
        if ($connection->isTableExists($templateTable)) {
            $connection->dropTable($templateTable);
        }
    }
}
