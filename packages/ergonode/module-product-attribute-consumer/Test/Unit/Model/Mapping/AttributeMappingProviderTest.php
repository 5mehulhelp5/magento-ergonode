<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Test\Unit\Model\Mapping;

use Magento\Framework\App\ResourceConnection;
use Ergonode\ProductAttribute\Model\Mapping\MappingStateBuilder;
use Ergonode\ProductAttribute\Model\ResourceModel\MappingReader;
use Ergonode\AttributeConsumer\Api\ErgonodeOptionProviderInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;
use Ergonode\ProductAttribute\Model\Mapping\ValueAdapterRegistry;
use Ergonode\ProductAttributeConsumer\Model\Mapping\AttributeMappingProvider;
use Ergonode\Attribute\Model\Mapping\AttributeTypeCompatibility;
use Ergonode\ProductAttribute\Model\Config\ProductAttributePolicy;
use Ergonode\AttributeConsumer\Model\Provider\ErgonodeAttributeProvider;
use Ergonode\ProductAttribute\Model\Provider\MagentoAttributeProvider;

class AttributeMappingProviderTest extends TestCase
{
    public function testReturnsBatchedOptionMappingProgressForCompatibleContexts(): void
    {
        $mappingSelect = $this->createSelect();
        $optionCountSelect = $this->createSelect();
        $mappedCountSelect = $this->createSelect();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->exactly(2))
            ->method('select')
            ->willReturnOnConsecutiveCalls($mappingSelect, $mappedCountSelect);
        $connection->expects($this->once())
            ->method('fetchAll')
            ->with($mappingSelect)
            ->willReturn(
                [
                [
                    'mapping_id' => '10',
                    'ergonode_attribute_code' => 'brand',
                    'magento_attribute_code' => 'manufacturer',
                ],
                [
                    'mapping_id' => '11',
                    'ergonode_attribute_code' => 'name',
                    'magento_attribute_code' => 'name',
                ],
                [
                    'mapping_id' => '12',
                    'ergonode_attribute_code' => 'visibility',
                    'magento_attribute_code' => 'visibility',
                ],
                [
                    'mapping_id' => '13',
                    'ergonode_attribute_code' => 'gallery',
                    'magento_attribute_code' => 'name',
                ],
                ]
            );
        $connection->expects($this->once())
            ->method('fetchPairs')
            ->willReturn(['10' => '12']);

        $ergonodeProvider = $this->createStub(ErgonodeAttributeProvider::class);
        $ergonodeProvider->method('getAttributeMap')->willReturn(
            [
            'brand' => ['label' => 'Marka', 'code' => 'brand', 'type' => 'select', 'scope' => 'global'],
            'name' => ['label' => 'Nazwa', 'code' => 'name', 'type' => 'text', 'scope' => 'local'],
            'visibility' => [
                'label' => 'Widoczność',
                'code' => 'visibility',
                'type' => 'select',
                'scope' => 'global',
            ],
            ]
        );
        $magentoProvider = $this->createStub(MagentoAttributeProvider::class);
        $magentoProvider->method('getAttributeMap')->willReturn(
            [
            'manufacturer' => [
                'label' => 'Manufacturer',
                'code' => 'manufacturer',
                'type' => 'select',
                'scope' => 'global',
                'has_custom_source' => false,
            ],
            'name' => [
                'label' => 'Product Name',
                'code' => 'name',
                'type' => 'text',
                'scope' => 'store',
                'has_custom_source' => false,
            ],
            'visibility' => [
                'label' => 'Visibility',
                'code' => 'visibility',
                'type' => 'select',
                'scope' => 'global',
                'has_custom_source' => true,
            ],
            ]
        );
        $compatibility = $this->createStub(AttributeTypeCompatibility::class);
        $compatibility->method('canMapAttributes')->willReturn(true);
        $compatibility->method('canMapOptions')->willReturnCallback(
            static fn (string $left, string $right): bool => $left === 'select' && $right === 'select'
        );
        $attributePolicy = $this->createStub(ProductAttributePolicy::class);
        $attributePolicy->method('isTypeMappable')->willReturn(true);
        $attributePolicy->method('isErgonodeMappable')->willReturnCallback(
            static fn (string $code): bool => $code !== 'gallery'
        );
        $attributePolicy->method('isMappable')->willReturn(true);

        $optionProvider = $this->createMock(ErgonodeOptionProviderInterface::class);
        $optionProvider->expects(self::once())->method('getOptionCounts')->with(['brand', 'visibility'])
            ->willReturn(['brand' => 14]);
        $provider = new AttributeMappingProvider(
            new MappingReader($this->createResourceConnection($connection)),
            $ergonodeProvider,
            $magentoProvider,
            new MappingStateBuilder($compatibility, $attributePolicy, new ValueAdapterRegistry()),
            $optionProvider
        );

        self::assertSame(
            [
            10 => ['mapped' => 12, 'total' => 14],
            12 => ['mapped' => 0, 'total' => 0],
            ],
            $provider->getOptionMappingProgress()
        );
        self::assertCount(3, $provider->getMappings());
        self::assertSame([10, 12], array_column($provider->getOptionAttributeContexts(), 'mapping_id'));
    }

    private function createSelect(): Select
    {
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('group')->willReturnSelf();
        $select->method('order')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        return $select;
    }

    private function createResourceConnection(AdapterInterface $connection): ResourceConnection
    {
        $resourceConnection = $this->createStub(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')->willReturnArgument(0);

        return $resourceConnection;
    }
}
