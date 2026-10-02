<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;

class Uninstall implements UninstallInterface
{
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        // This module owns no tables. Product data, files and configuration are retained.
    }
}
