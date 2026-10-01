<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisherAdminUi\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;

class Uninstall implements UninstallInterface
{
    private const array BOOKMARK_NAMESPACES = [
        'ergonode_product_publication_job_listing',
        'ergonode_product_publication_item_listing',
    ];

    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        unset($context);
        $connection = $setup->getConnection();
        $connection->startSetup();

        try {
            $bookmarkTable = $setup->getTable('ui_bookmark');
            if ($connection->isTableExists($bookmarkTable)) {
                $connection->delete($bookmarkTable, ['namespace IN (?)' => self::BOOKMARK_NAMESPACES]);
            }
        } finally {
            $connection->endSetup();
        }
    }
}
