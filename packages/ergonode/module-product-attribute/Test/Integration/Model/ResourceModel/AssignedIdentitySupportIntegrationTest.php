<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Test\Integration\Model\ResourceModel;

use Ergonode\Product\Api\ProductIdentityModeProviderInterface;
use Ergonode\ProductAttribute\Api\CompleteMappingProviderInterface;
use Ergonode\ProductAttribute\Api\SkuIdentityMappingProviderInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[DbIsolation(true)]
class AssignedIdentitySupportIntegrationTest extends TestCase
{
    #[Config('ergonode_products/identity/sku_mode', 'assigned')]
    public function testSharedConfigurationAndCompleteMappingWorkThroughDi(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $resource = $objectManager->get(ResourceConnection::class);
        $table = $resource->getTableName('ergonode_product_attribute_mapping');
        $resource->getConnection()->delete($table);
        $resource->getConnection()->insert($table, [
            'ergonode_attribute_code' => 'magento_sku',
            'magento_attribute_code' => 'sku',
            'ergonode_type' => 'text',
            'magento_type' => 'text',
            'content_hash' => hash('sha256', 'magento_sku'),
        ]);
        $provider = $objectManager->get(ProductIdentityModeProviderInterface::class);

        self::assertTrue($provider->isAssignedModeAvailable());
        self::assertSame('assigned', $provider->getMode());
        $provider->assertModeAvailable('assigned');
        self::assertSame(
            'magento_sku',
            $objectManager->get(SkuIdentityMappingProviderInterface::class)->getMapping()['ergonode_attribute_code']
        );
        $mappings = $objectManager->get(CompleteMappingProviderInterface::class)->getMappings();
        self::assertSame(['sku'], array_column($mappings, 'magento_attribute_code'));
    }

    public function testIncompleteSkuMappingBlocksAssignedSynchronization(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $resource = $objectManager->get(ResourceConnection::class);
        $table = $resource->getTableName('ergonode_product_attribute_mapping');
        $resource->getConnection()->delete($table);
        $resource->getConnection()->insert($table, [
            'ergonode_attribute_code' => 'magento_sku',
            'magento_attribute_code' => 'sku',
            'ergonode_type' => 'text',
            'magento_type' => 'text',
            'status' => 'incomplete',
            'content_hash' => hash('sha256', 'magento_sku'),
        ]);

        $this->expectException(LocalizedException::class);
        $objectManager->get(ProductIdentityModeProviderInterface::class)->assertModeAvailable('assigned');
    }
}
