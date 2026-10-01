<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Setup;

use Ergonode\ProductPublisher\Setup\Uninstall\RuntimeRecordCleaner;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;

class Uninstall implements UninstallInterface
{
    public function __construct(
        private readonly RuntimeRecordCleaner $runtimeRecordCleaner
    ) {
    }

    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        unset($context);
        $connection = $setup->getConnection();
        $connection->startSetup();

        try {
            $this->runtimeRecordCleaner->execute($setup);
            $resultTable = $setup->getTable('ergonode_product_publication_result');
            if ($connection->isTableExists($resultTable)) {
                $connection->dropTable($resultTable);
            }
            $authorizationTable = $setup->getTable('authorization_rule');
            if ($connection->isTableExists($authorizationTable)) {
                $connection->delete($authorizationTable, ['resource_id LIKE ?' => 'Ergonode_ProductPublisher::%']);
            }
        } finally {
            $connection->endSetup();
        }
    }
}
