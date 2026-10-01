<?php

declare(strict_types=1);

namespace PackHauer\UnitAttribute\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;
use PackHauer\UnitAttribute\Setup\Uninstall\ProductAttributeCleaner;
use Throwable;

class Uninstall implements UninstallInterface
{
    public function __construct(
        private readonly ProductAttributeCleaner $productAttributeCleaner
    ) {
    }

    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        unset($context);
        $connection = $setup->getConnection();
        $connection->beginTransaction();

        try {
            $this->productAttributeCleaner->execute($setup);
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }
}
