<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Setup;

use Ergonode\ProductMedia\Model\Config\GalleryConfig;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;

class Uninstall implements UninstallInterface
{
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        unset($context);
        $connection = $setup->getConnection();
        $table = $setup->getTable('core_config_data');
        if ($connection->isTableExists($table)) {
            $connection->delete($table, ['path IN (?)' => [
                GalleryConfig::XML_PATH_ENABLED, GalleryConfig::XML_PATH_GALLERY_ATTRIBUTE,
                GalleryConfig::XML_PATH_IMAGES, GalleryConfig::XML_PATH_ROLE, GalleryConfig::XML_PATH_POSITION,
            ]]);
        }
    }
}
