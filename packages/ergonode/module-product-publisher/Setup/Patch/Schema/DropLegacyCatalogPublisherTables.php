<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Setup\Patch\Schema;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;

class DropLegacyCatalogPublisherTables implements SchemaPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $connection->startSetup();

        try {
            $connection->dropTable(
                $this->moduleDataSetup->getTable('ergonode_product_publication_item')
            );
            $connection->dropTable(
                $this->moduleDataSetup->getTable('ergonode_product_publication_job')
            );
        } finally {
            $connection->endSetup();
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
