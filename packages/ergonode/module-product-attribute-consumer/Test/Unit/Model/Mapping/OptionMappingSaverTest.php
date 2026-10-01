<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Test\Unit\Model\Mapping;

use Ergonode\ProductAttributeConsumer\Model\Mapping\AttributeMappingProvider;
use Ergonode\Attribute\Model\Mapping\AttributeTypeCompatibility;
use Ergonode\ProductAttributeConsumer\Model\Mapping\OptionMappingMagentoSyncer;
use Ergonode\ProductAttribute\Model\Mapping\OptionMappingPersister;
use Ergonode\ProductAttributeConsumer\Model\Mapping\OptionMappingSaver;
use Ergonode\Core\Api\MappingRowsPersisterInterface;
use Ergonode\ProductAttribute\Api\MappingReaderInterface;
use Ergonode\ProductAttribute\Model\Provider\MagentoAttributeProvider;
use Ergonode\ProductAttribute\Model\Mapping\OptionMappingSaver as MappingSaver;
use Ergonode\Core\Api\MappingVisibilitySaverInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OptionMappingSaverTest extends TestCase
{
    public function testSavesExistingOptionWithoutStartingAutomaticSynchronization(): void
    {
        $attributeMapping = $this->customSourceAttributeMapping();
        $attributeMappingProvider = $this->createMock(AttributeMappingProvider::class);
        $attributeMappingProvider->expects(self::once())
            ->method('getMappingRow')
            ->with(12)
            ->willReturn($attributeMapping);
        $typeCompatibility = $this->createMock(AttributeTypeCompatibility::class);
        $typeCompatibility->expects(self::exactly(2))
            ->method('canMapOptions')
            ->with('select', 'select')
            ->willReturn(true);

        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::exactly(2))->method('beginTransaction');
        $connection->expects(self::once())->method('select')->willReturn($select);
        $connection->expects(self::once())->method('fetchAll')->with($select)->willReturn([]);
        $connection->expects(self::exactly(2))->method('commit');
        $connection->expects(self::never())->method('rollBack');

        $rowsPersister = $this->createMock(MappingRowsPersisterInterface::class);
        $rowsPersister->expects(self::once())
            ->method('hash')
            ->with(
                [
                'attribute_mapping_id' => 12,
                'ergonode_option_code' => 'enabled',
                'magento_option_id' => 1,
                'status' => 'complete',
                ]
            )
            ->willReturn('mapping-hash');
        $rowsPersister->expects(self::once())
            ->method('persist')
            ->with(
                $connection,
                'ergonode_product_option_mapping',
                [],
                [
                'full:enabled|1' => [
                    'attribute_mapping_id' => 12,
                    'ergonode_option_code' => 'enabled',
                    'magento_option_id' => 1,
                    'status' => 'complete',
                    'content_hash' => 'mapping-hash',
                    'sort_order' => 0,
                ],
                ]
            )
            ->willReturn(['inserted' => 1, 'updated' => 0, 'deleted' => 0, 'unchanged' => 0]);
        $visibilitySaver = $this->createMock(MappingVisibilitySaverInterface::class);
        $visibilitySaver->expects(self::once())->method('saveMany')->with([]);
        $magentoSyncer = $this->createMock(OptionMappingMagentoSyncer::class);
        $magentoSyncer->expects(self::never())->method('createPendingMagentoOption');
        $saver = new OptionMappingSaver(
            $this->createResourceConnection($connection),
            $attributeMappingProvider,
            $typeCompatibility,
            $magentoSyncer,
            $this->neutralSaver(
                $this->createResourceConnection($connection),
                $visibilitySaver,
                $typeCompatibility,
                new OptionMappingPersister(
                    $this->createResourceConnection($connection),
                    $rowsPersister
                ),
                $this->createStub(LoggerInterface::class)
            )
        );

        self::assertSame(
            [
            'inserted' => 1,
            'updated' => 0,
            'deleted' => 0,
            'unchanged' => 0,
            'option_labels_updated' => 0,
            'options_created' => 0,
            'options_linked' => 0,
            'option_sort_order_updated' => 0,
            'option_sync_unchanged' => 0,
            'option_sync_skipped' => 0,
            'option_sync_errors' => 0,
            ],
            $saver->save(
                12,
                [[
                'left' => ['code' => 'enabled'],
                'right' => ['code' => 'option_1'],
                ]],
                []
            )
        );
    }

    public function testRejectsNewOptionCreationForMagentoCustomSourceAttribute(): void
    {
        $attributeMappingProvider = $this->createStub(AttributeMappingProvider::class);
        $attributeMappingProvider->method('getMappingRow')->willReturn($this->customSourceAttributeMapping());
        $typeCompatibility = $this->createStub(AttributeTypeCompatibility::class);
        $typeCompatibility->method('canMapOptions')->willReturn(true);
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('rollBack');
        $connection->expects(self::never())->method('commit');
        $magentoSyncer = $this->createMock(OptionMappingMagentoSyncer::class);
        $magentoSyncer->expects(self::never())->method('createPendingMagentoOption');
        $rowsPersister = $this->createMock(MappingRowsPersisterInterface::class);
        $rowsPersister->expects(self::never())->method('persist');

        $saver = new OptionMappingSaver(
            $this->createResourceConnection($connection),
            $attributeMappingProvider,
            $typeCompatibility,
            $magentoSyncer,
            $this->neutralSaver(
                $this->createResourceConnection($connection),
                $this->createStub(MappingVisibilitySaverInterface::class),
                $typeCompatibility,
                new OptionMappingPersister(
                    $this->createResourceConnection($connection),
                    $rowsPersister
                ),
                $this->createStub(LoggerInterface::class)
            )
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('New options cannot be created for this native Magento attribute.');

        $saver->save(
            12,
            [[
            'left' => ['code' => 'enabled'],
            'right' => ['code' => 'pending_enabled', 'pending_create' => true],
            ]],
            []
        );
    }

    public function testReportsOptionCreatedThroughSharedManualAndAutomaticCreator(): void
    {
        $attributeMapping = $this->customSourceAttributeMapping();
        $attributeMapping['magento_has_custom_source'] = false;
        $attributeMappingProvider = $this->createStub(AttributeMappingProvider::class);
        $attributeMappingProvider->method('getMappingRow')->willReturn($attributeMapping);
        $typeCompatibility = $this->createStub(AttributeTypeCompatibility::class);
        $typeCompatibility->method('canMapOptions')->willReturn(true);
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::exactly(2))->method('beginTransaction');
        $connection->expects(self::exactly(2))->method('commit');
        $magentoSyncer = $this->createMock(OptionMappingMagentoSyncer::class);
        $magentoSyncer->expects(self::once())->method('createPendingMagentoOption')->willReturn(
            [
            'code' => 'option_27',
            'created' => true,
            ]
        );
        $persister = $this->createMock(OptionMappingPersister::class);
        $persister->expects(self::once())->method('hash')->willReturn('mapping-hash');
        $persister->expects(self::once())->method('replace')->willReturn(
            [
            'inserted' => 1,
            'updated' => 0,
            'deleted' => 0,
            'unchanged' => 0,
            ]
        );

        $stats = (new OptionMappingSaver(
            $this->createResourceConnection($connection),
            $attributeMappingProvider,
            $typeCompatibility,
            $magentoSyncer,
            $this->neutralSaver(
                $this->createResourceConnection($connection),
                $this->createStub(MappingVisibilitySaverInterface::class),
                $typeCompatibility,
                $persister,
                $this->createStub(LoggerInterface::class)
            )
        ))->save(
            12,
            [[
            'left' => ['code' => 'enabled'],
            'right' => ['code' => 'pending_enabled', 'pending_create' => true],
            ]],
            []
        );

        self::assertSame(1, $stats['options_created']);
        self::assertSame(1, $stats['inserted']);
    }

    /**
     * @return array<string, int|string|bool>
     */
    private function customSourceAttributeMapping(): array
    {
        return [
            'mapping_id' => 12,
            'ergonode_attribute_code' => 'status',
            'magento_attribute_code' => 'status',
            'ergonode_type' => 'select',
            'magento_type' => 'select',
            'magento_has_custom_source' => true,
        ];
    }

    private function neutralSaver(
        ResourceConnection $resource,
        MappingVisibilitySaverInterface $visibility,
        AttributeTypeCompatibility $compatibility,
        OptionMappingPersister $persister,
        LoggerInterface $logger
    ): MappingSaver {
        $reader = $this->createStub(MappingReaderInterface::class);
        $reader->method('getAttributeRow')->willReturn($this->customSourceAttributeMapping());
        $magento = $this->createStub(MagentoAttributeProvider::class);
        $magento->method('getAttribute')->willReturn(['code' => 'status', 'type' => 'select']);

        return new MappingSaver($resource, $reader, $magento, $visibility, $compatibility, $persister, $logger);
    }

    private function createResourceConnection(AdapterInterface $connection): ResourceConnection
    {
        $resourceConnection = $this->createStub(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')->willReturnArgument(0);

        return $resourceConnection;
    }
}
