<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Test\Unit\Model\Sync;

use Ergonode\ProductAttributeConsumer\Model\Config\ProductAttributeConfigProvider;
use Ergonode\ProductAttribute\Model\Mapping\OptionMappingPersister;
use Ergonode\ProductAttributeConsumer\Model\Provider\MagentoOptionCreator;
use Ergonode\ProductAttributeConsumer\Model\Sync\MagentoOptionSyncResource;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;

class MagentoOptionSyncResourceTest extends TestCase
{
    public function testLoadsDefaultAndExplicitStoreLabelsInTwoBulkQueries(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('joinLeft')->willReturnSelf();
        $select->method('joinInner')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::exactly(2))->method('select')->willReturn($select);
        $connection->expects(self::exactly(2))->method('fetchAll')->willReturnOnConsecutiveCalls(
            [
                ['option_id' => 10, 'sort_order' => 1, 'label' => 'Blue'],
                ['option_id' => 11, 'sort_order' => 2, 'label' => 'Green'],
                ['option_id' => 12, 'sort_order' => 3, 'label' => 'Red'],
            ],
            [
                ['option_id' => 10, 'store_id' => 2, 'value' => 'Niebieski'],
                ['option_id' => 11, 'store_id' => 1, 'value' => 'Green'],
            ]
        );
        $resourceConnection = $this->createStub(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')->willReturnArgument(0);
        $resource = new MagentoOptionSyncResource(
            $resourceConnection,
            $this->createStub(ProductAttributeConfigProvider::class),
            $this->createStub(MagentoOptionCreator::class),
            $this->createStub(OptionMappingPersister::class)
        );

        $options = $resource->loadMagentoOptions(97);

        self::assertSame([2 => 'Niebieski'], $options['by_id'][10]['store_labels']);
        self::assertSame([], $options['by_id'][12]['store_labels']);
    }

    public function testSyncSortOrderWritesOnlyWhenValueChanges(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())
            ->method('update')
            ->with(
                'eav_attribute_option',
                ['sort_order' => 4],
                ['option_id = ?' => 10]
            );
        $resourceConnection = $this->createStub(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')->willReturnArgument(0);
        $config = $this->createStub(ProductAttributeConfigProvider::class);
        $config->method('shouldSynchronizeOptionSortOrder')->willReturn(true);
        $resource = new MagentoOptionSyncResource(
            $resourceConnection,
            $config,
            $this->createStub(MagentoOptionCreator::class),
            $this->createStub(OptionMappingPersister::class)
        );
        $options = [
            'by_id' => [
                10 => [
                    'option_id' => 10,
                    'sort_order' => 2,
                    'label' => 'Blue',
                ],
            ],
            'by_label' => ['blue' => 10],
        ];

        self::assertTrue($resource->syncSortOrder(10, 4, $options));
        self::assertSame(4, $options['by_id'][10]['sort_order']);
        self::assertFalse($resource->syncSortOrder(10, 4, $options));
    }

    public function testConfiguredSortOrderLeavesMagentoUntouchedWhenDisabled(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::never())->method('update');
        $resourceConnection = $this->createStub(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $config = $this->createStub(ProductAttributeConfigProvider::class);
        $config->method('shouldSynchronizeOptionSortOrder')->willReturn(false);
        $resource = new MagentoOptionSyncResource(
            $resourceConnection,
            $config,
            $this->createStub(MagentoOptionCreator::class),
            $this->createStub(OptionMappingPersister::class)
        );
        $options = [
            'by_id' => [10 => ['option_id' => 10, 'sort_order' => 2, 'label' => 'Blue']],
        ];

        self::assertFalse($resource->syncSortOrder(10, 4, $options));
        self::assertSame(2, $options['by_id'][10]['sort_order']);
    }

    public function testDelegatesCreationAndMappingPersistenceToSharedComponents(): void
    {
        $createdOption = [
            'option_id' => 27,
            'label' => 'Blue',
            'code' => 'option_27',
            'scope' => 'ID 27',
            'type' => 'option',
            'source' => 'magento',
            'created' => true,
        ];
        $creator = $this->createMock(MagentoOptionCreator::class);
        $creator->expects(self::once())
            ->method('create')
            ->with('color', 'Blue', 3)
            ->willReturn($createdOption);
        $persister = $this->createMock(OptionMappingPersister::class);
        $persister->expects(self::once())->method('loadByOptionCode')->with(14)->willReturn([]);
        $persister->expects(self::once())
            ->method('upsertComplete')
            ->with(14, 'blue', 27, 3, self::isArray())
            ->willReturn('inserted');
        $resource = new MagentoOptionSyncResource(
            $this->createStub(ResourceConnection::class),
            $this->createStub(ProductAttributeConfigProvider::class),
            $creator,
            $persister
        );
        $existingMappings = $resource->loadExistingMappings(14);

        self::assertSame($createdOption, $resource->createMagentoOption('color', 'Blue', 3));
        self::assertSame('inserted', $resource->syncMapping(14, 'blue', 27, 3, $existingMappings));
    }
}
