<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Setup;

use Ergonode\ProductConsumer\Setup\Uninstall\RuntimeRecordCleaner;
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
        } finally {
            $connection->endSetup();
        }
    }
}
