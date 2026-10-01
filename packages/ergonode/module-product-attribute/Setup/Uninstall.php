<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Setup;

use Ergonode\ProductAttribute\Model\Config\ProductAttributePolicy;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;

class Uninstall implements UninstallInterface
{
    private const array ACL_RESOURCES = [
        'Ergonode_AttributeConsumer::attribute_mapping',
        'Ergonode_AttributeConsumer::attribute_save',
        'Ergonode_AttributeConsumer::option_mapping',
        'Ergonode_AttributeConsumer::option_save',
    ];

    private const array OWNED_TABLES = [
        'ergonode_product_option_mapping',
        'ergonode_product_attribute_mapping',
    ];

    private const string LEGACY_XML_PATH_ASSIGNED_SKU_CAPABILITY_VERIFIED =
        'ergonode_products/attributes/assigned_sku_capability_verified';
    private const string LEGACY_XML_PATH_SKU_MODE = 'ergonode_products/attributes/sku_mode';

    private const array CONFIGURATION_PATHS = [
        self::LEGACY_XML_PATH_ASSIGNED_SKU_CAPABILITY_VERIFIED,
        self::LEGACY_XML_PATH_SKU_MODE,
        ProductAttributePolicy::XML_PATH_URL_KEY,
        ProductAttributePolicy::XML_PATH_PRICE_MODE,
        ProductAttributePolicy::XML_PATH_PRICE_DEFAULT,
        ProductAttributePolicy::XML_PATH_STATUS_MODE,
        ProductAttributePolicy::XML_PATH_STATUS_DEFAULT,
        ProductAttributePolicy::XML_PATH_VISIBILITY_MODE,
        ProductAttributePolicy::XML_PATH_VISIBILITY_DEFAULT,
    ];

    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        unset($context);
        $connection = $setup->getConnection();
        $connection->startSetup();

        try {
            $authorizationRuleTable = $setup->getTable('authorization_rule');
            if ($connection->isTableExists($authorizationRuleTable)) {
                $connection->delete($authorizationRuleTable, ['resource_id IN (?)' => self::ACL_RESOURCES]);
            }
            $configurationTable = $setup->getTable('core_config_data');
            if ($connection->isTableExists($configurationTable)) {
                $connection->delete($configurationTable, ['path IN (?)' => self::CONFIGURATION_PATHS]);
            }
            $visibilityTable = $setup->getTable('ergonode_mapping_visibility');
            if ($connection->isTableExists($visibilityTable)) {
                $connection->delete($visibilityTable, ['entity_type IN (?)' => ['attribute', 'option']]);
            }
            foreach (self::OWNED_TABLES as $table) {
                $tableName = $setup->getTable($table);
                if ($connection->isTableExists($tableName)) {
                    $connection->dropTable($tableName);
                }
            }
        } finally {
            $connection->endSetup();
        }
    }
}
