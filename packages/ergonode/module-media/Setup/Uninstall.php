<?php

declare(strict_types=1);

namespace Ergonode\Media\Setup;

use Ergonode\Media\Setup\Uninstall\FilesystemCleaner;
use Ergonode\Media\Setup\Uninstall\ProductReferenceCleaner;
use Ergonode\Media\Setup\Uninstall\RuntimeRecordCleaner;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;

class Uninstall implements UninstallInterface
{
    public function __construct(
        private readonly ProductReferenceCleaner $productReferenceCleaner,
        private readonly RuntimeRecordCleaner $runtimeRecordCleaner,
        private readonly FilesystemCleaner $filesystemCleaner
    ) {
    }

    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        $connection = $setup->getConnection();
        $connection->startSetup();
        try {
            $this->productReferenceCleaner->execute($setup);
            $this->runtimeRecordCleaner->execute($setup);
        } finally {
            $connection->endSetup();
        }

        $this->filesystemCleaner->execute();
    }
}
