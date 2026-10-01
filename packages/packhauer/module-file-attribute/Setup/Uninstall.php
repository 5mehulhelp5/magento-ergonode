<?php

declare(strict_types=1);

namespace PackHauer\FileAttribute\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;
use PackHauer\FileAttribute\Setup\Uninstall\FilesystemCleaner;
use PackHauer\FileAttribute\Setup\Uninstall\ProductAttributeCleaner;
use Throwable;

class Uninstall implements UninstallInterface
{
    public function __construct(
        private readonly ProductAttributeCleaner $productAttributeCleaner,
        private readonly FilesystemCleaner $filesystemCleaner
    ) {
    }

    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        unset($context);
        $connection = $setup->getConnection();
        $connection->beginTransaction();

        try {
            $cleanup = $this->productAttributeCleaner->execute($setup);
            $this->removeAclRules($setup);
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        $this->filesystemCleaner->execute($cleanup);
    }

    private function removeAclRules(SchemaSetupInterface $setup): void
    {
        $connection = $setup->getConnection();
        $authorizationTable = $setup->getTable('authorization_rule');
        if (!$connection->isTableExists($authorizationTable)) {
            return;
        }

        foreach (['PackHauer_FileAttribute::%', 'Vendivo_FileAttribute::%'] as $resourcePattern) {
            $connection->delete($authorizationTable, ['resource_id LIKE ?' => $resourcePattern]);
        }
    }
}
