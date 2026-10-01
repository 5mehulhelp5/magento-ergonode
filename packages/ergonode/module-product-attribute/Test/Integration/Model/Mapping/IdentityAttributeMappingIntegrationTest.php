<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Test\Integration\Model\Mapping;

use Ergonode\ProductAttribute\Api\CompleteMappingProviderInterface;
use Ergonode\ProductAttribute\Api\ProductAttributeCodeMappingProviderInterface;
use Ergonode\ProductAttribute\Model\Mapping\AttributeMappingSaver;
use Ergonode\ProductAttribute\Model\Provider\MagentoAttributeProvider;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class IdentityAttributeMappingIntegrationTest extends TestCase
{
    #[Config('ergonode_products/identity/sku_mode', 'mapped')]
    #[Config('ergonode_products/identity/magento_attribute', 'name')]
    public function testExistingMappingIsPreservedButExcludedFromAllOrdinaryReads(): void
    {
        $objects = Bootstrap::getObjectManager();
        $resource = $objects->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $table = $resource->getTableName('ergonode_product_attribute_mapping');
        $connection->delete($table);
        $connection->insert($table, [
            'ergonode_attribute_code' => 'remote_identity',
            'magento_attribute_code' => 'name',
            'ergonode_type' => 'text',
            'magento_type' => 'text',
            'status' => 'complete',
            'content_hash' => hash('sha256', 'identity'),
            'sort_order' => 10,
        ]);
        $before = $connection->fetchRow($connection->select()->from($table));
        $objects->get(AttributeMappingSaver::class)->save([], []);
        self::assertSame($before, $connection->fetchRow($connection->select()->from($table)));
        $provider = $objects->get(CompleteMappingProviderInterface::class);
        self::assertSame([], $provider->getMappings());
        self::assertSame([], $provider->getMappings('import'));
        self::assertSame([], $provider->getMappings('publish'));
        self::assertSame([], $objects->get(ProductAttributeCodeMappingProviderInterface::class)
            ->getMagentoAttributeCodes(['remote_identity']));
        $metadata = $objects->get(MagentoAttributeProvider::class);
        self::assertArrayNotHasKey('name', $metadata->getAttributeMap());
        self::assertArrayHasKey('name', $metadata->getAttributeMap(true));
    }

    #[Config('ergonode_products/identity/sku_mode', 'mapped')]
    #[Config('ergonode_products/identity/magento_attribute', 'name')]
    #[DataProvider('saveMethodProvider')]
    public function testBothSaveEntrypointsRejectIdentityMapping(string $method): void
    {
        $saver = Bootstrap::getObjectManager()->get(AttributeMappingSaver::class);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Magento attribute "name" is not available for mapping.');
        $mappings = [[
            'left' => ['code' => 'remote_identity', 'type' => 'text'],
            'right' => ['code' => 'name', 'type' => 'text'],
        ]];
        if ($method === 'save') {
            $saver->save($mappings, []);
        } else {
            $saver->saveAdditions($mappings);
        }
    }

    /** @return array<string, array{string}> */
    public static function saveMethodProvider(): array
    {
        return ['manual save' => ['save'], 'automatic additions' => ['saveAdditions']];
    }
}
