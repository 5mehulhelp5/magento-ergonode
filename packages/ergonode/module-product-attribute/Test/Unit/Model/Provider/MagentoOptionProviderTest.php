<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Test\Unit\Model\Provider;

use Ergonode\Core\Api\MappingVisibilityProviderInterface;
use Ergonode\ProductAttribute\Model\Provider\MagentoAttributeProvider;
use Ergonode\ProductAttribute\Model\Provider\MagentoOptionProvider;
use Ergonode\ProductAttribute\Model\Provider\MagentoVisibilityOptionProvider;
use Magento\Eav\Api\AttributeOptionManagementInterface;
use Magento\Eav\Api\Data\AttributeOptionInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;

class MagentoOptionProviderTest extends TestCase
{
    public function testUsesStoreZeroAndExplicitStoreRowsInsteadOfCurrentStoreLabel(): void
    {
        $option = $this->createStub(AttributeOptionInterface::class);
        $option->method('getValue')->willReturn('10');
        $option->method('getLabel')->willReturn('Current store label');
        $management = $this->createStub(AttributeOptionManagementInterface::class);
        $management->method('getItems')->willReturn([$option]);
        $attribute = $this->createStub(MagentoAttributeProvider::class);
        $attribute->method('getAttribute')->willReturn(['type' => 'select', 'has_custom_source' => false]);
        $visibility = $this->createStub(MagentoVisibilityOptionProvider::class);
        $visibility->method('isSupported')->willReturn(false);
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('fetchAll')->willReturn([
            ['option_id' => 10, 'store_id' => 0, 'value' => 'Blue'],
            ['option_id' => 10, 'store_id' => 2, 'value' => 'Niebieski'],
        ]);
        $connection->method('select')->willReturn($select);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        $provider = new MagentoOptionProvider(
            $management,
            $visibility,
            $attribute,
            $this->createStub(MappingVisibilityProviderInterface::class),
            $resource
        );

        $options = $provider->getOptions('color');

        self::assertSame('Blue', $options[0]['label']);
        self::assertSame([2 => 'Niebieski'], $options[0]['store_labels']);
    }
}
