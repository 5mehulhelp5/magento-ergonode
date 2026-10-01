<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Test\Unit\Model\Product;

use Ergonode\Attribute\Api\ErgonodeAttributeTypeResolverInterface;
use Ergonode\AttributeConsumer\Api\AttributeAvailabilityInterface;
use Ergonode\Core\Model\Report\ChangeReport;

use Ergonode\ProductAttribute\Api\CompleteMappingProviderInterface;
use Ergonode\ProductAttribute\Model\Config\ProductAttributePolicy;
use Ergonode\ProductAttribute\Model\Mapping\ValueAdapterRegistry;
use Ergonode\ProductAttribute\Model\Provider\MagentoAttributeProvider;
use Ergonode\ProductAttribute\Model\ResourceModel\CompleteMappingProvider;
use Ergonode\ProductAttributeConsumer\Model\Product\ProductMappingProvider;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class ProductMappingProviderTest extends TestCase
{
    public function testDeletedSourceMappingIsNotPassedToValueImporter(): void
    {
        $mappings = $this->createStub(CompleteMappingProviderInterface::class);
        $mappings->method('getMappings')->willReturn([[
            'mapping_id' => 12, 'ergonode_attribute_code' => 'deleted',
            'magento_attribute_code' => 'description', 'ergonode_type' => 'text', 'magento_type' => 'text',
            'option_ids' => [],
        ]]);
        $availability = $this->createStub(AttributeAvailabilityInterface::class);
        $availability->method('getCodes')->willReturn(['existing']);
        $resource = $this->createMock(ResourceConnection::class);
        $resource->expects(self::never())->method('getConnection');
        $report = $this->createMock(ChangeReport::class);
        $report->expects(self::once())->method('add');
        $provider = new ProductMappingProvider(
            $report,
            $availability,
            $resource,
            new Json(),
            $this->createStub(MagentoAttributeProvider::class),
            $mappings
        );

        self::assertSame([], $provider->getMappings());
    }

    public function testReservedGallerySourceIsNotAvailableToProductSynchronization(): void
    {
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('order')->willReturnSelf();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('select')->willReturn($select);
        $connection->expects(self::once())->method('fetchAll')->with($select)->willReturn(
            [[
            'mapping_id' => '13',
            'ergonode_attribute_code' => 'gallery',
            'magento_attribute_code' => 'name',
            'ergonode_type' => 'gallery',
            'magento_type' => 'text',
            ]]
        );
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        $policy = $this->createMock(ProductAttributePolicy::class);
        $policy->method('isTypeMappable')->willReturn(true);
        $policy->expects(self::once())
            ->method('isErgonodeMappable')
            ->with('gallery')
            ->willReturn(false);
        $policy->expects(self::never())->method('isMappable');
        $magentoAttributeProvider = $this->createMock(MagentoAttributeProvider::class);
        $magentoAttributeProvider->expects(self::never())->method('getAttribute');

        $provider = new ProductMappingProvider(
            $this->createStub(ChangeReport::class),
            $this->createStub(AttributeAvailabilityInterface::class),
            $resource,
            new Json(),
            $magentoAttributeProvider,
            new CompleteMappingProvider(
                $resource,
                $policy,
                $this->createStub(ErgonodeAttributeTypeResolverInterface::class),
                new ValueAdapterRegistry()
            )
        );

        self::assertSame([], $provider->getMappings());
    }
}
