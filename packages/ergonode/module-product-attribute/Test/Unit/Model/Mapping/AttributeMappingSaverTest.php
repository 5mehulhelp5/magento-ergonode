<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Test\Unit\Model\Mapping;

use Ergonode\ProductAttribute\Model\Mapping\AttributeMappingNormalizer;
use Ergonode\ProductAttribute\Model\Mapping\AttributeMappingSaver;
use Ergonode\Attribute\Model\Mapping\AttributeTypeCompatibility;
use Ergonode\ProductAttribute\Model\Mapping\OptionMappingPersister;
use Ergonode\Core\Api\MappingRowsPersisterInterface;
use Ergonode\Core\Api\MappingVisibilitySaverInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AttributeMappingSaverTest extends TestCase
{
    public function testRemovesOptionArtifactsBeforePersistingDeletedAttributeMapping(): void
    {
        $select = $this->createMock(Select::class);
        $select->expects(self::once())
            ->method('from')
            ->with('ergonode_product_attribute_mapping')
            ->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('select')->willReturn($select);
        $connection->expects(self::once())->method('fetchAll')->with($select)->willReturn(
            [[
            'mapping_id' => 12,
            'ergonode_attribute_code' => 'color',
            'magento_attribute_code' => 'color',
            'ergonode_type' => 'select',
            'magento_type' => 'select',
            ]]
        );
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('commit');

        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $normalizer = $this->createMock(AttributeMappingNormalizer::class);
        $normalizer->expects(self::once())->method('normalize')->with([])->willReturn([]);
        $normalizer->expects(self::once())->method('key')->with('color', 'color')->willReturn('full:color|color');

        $visibility = $this->createMock(MappingVisibilitySaverInterface::class);
        $visibility->expects(self::exactly(2))
            ->method('deleteByParentIdentifier')
            ->willReturnCallback(
                static function (string $entityType, string $source, string $identifier): void {
                    self::assertSame('option', $entityType);
                    self::assertSame('color', $identifier);
                    self::assertContains($source, ['ergo', 'magento']);
                }
            );
        $visibility->expects(self::once())->method('saveMany')->with([]);

        $persister = $this->createMock(MappingRowsPersisterInterface::class);
        $persister->expects(self::once())
            ->method('persist')
            ->with(
                $connection,
                'ergonode_product_attribute_mapping',
                ['full:color|color' => [
                    'mapping_id' => 12,
                    'ergonode_attribute_code' => 'color',
                    'magento_attribute_code' => 'color',
                    'ergonode_type' => 'select',
                    'magento_type' => 'select',
                ]],
                []
            )
            ->willReturn(['inserted' => 0, 'updated' => 0, 'deleted' => 1, 'unchanged' => 0]);
        $optionMappingPersister = $this->createMock(OptionMappingPersister::class);
        $optionMappingPersister->expects(self::once())
            ->method('deleteByAttributeMappingIds')
            ->with([12]);

        $saver = new AttributeMappingSaver(
            $resource,
            $visibility,
            $normalizer,
            $this->createStub(AttributeTypeCompatibility::class),
            $persister,
            $optionMappingPersister,
            $this->createStub(LoggerInterface::class)
        );

        self::assertSame(
            ['inserted' => 0, 'updated' => 0, 'deleted' => 1, 'unchanged' => 0],
            $saver->save([], [])
        );
    }

    public function testAutomaticAdditionCompletesDraftWithoutDeletingOtherMappings(): void
    {
        $select = $this->createMock(Select::class);
        $select->expects(self::once())
            ->method('from')
            ->with('ergonode_product_attribute_mapping')
            ->willReturnSelf();
        $draft = [
            'mapping_id' => 12,
            'ergonode_attribute_code' => 'color',
            'magento_attribute_code' => null,
            'ergonode_type' => 'select',
            'magento_type' => null,
            'status' => 'draft',
            'content_hash' => 'draft-hash',
            'sort_order' => 3,
        ];
        $addition = [
            'ergonode_attribute_code' => 'color',
            'magento_attribute_code' => 'color',
            'ergonode_type' => 'select',
            'magento_type' => 'select',
            'status' => 'complete',
            'content_hash' => 'complete-hash',
            'sort_order' => 0,
        ];

        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('select')->willReturn($select);
        $connection->expects(self::once())->method('fetchAll')->with($select)->willReturn([$draft]);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('commit');

        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $normalizer = $this->createMock(AttributeMappingNormalizer::class);
        $normalizer->expects(self::once())->method('normalize')->willReturn(['full:color|color' => $addition]);
        $normalizer->expects(self::once())->method('key')->with('color', '')->willReturn('ergo:color');

        $visibility = $this->createMock(MappingVisibilitySaverInterface::class);
        $visibility->expects(self::never())->method('saveMany');
        $visibility->expects(self::never())->method('deleteByParentIdentifier');

        $persister = $this->createMock(MappingRowsPersisterInterface::class);
        $persister->expects(self::once())
            ->method('persist')
            ->with(
                $connection,
                'ergonode_product_attribute_mapping',
                ['ergo:color' => $draft],
                ['full:color|color' => array_merge($addition, ['sort_order' => 3])]
            )
            ->willReturn(['inserted' => 1, 'updated' => 0, 'deleted' => 1, 'unchanged' => 0]);

        $saver = new AttributeMappingSaver(
            $resource,
            $visibility,
            $normalizer,
            $this->createStub(AttributeTypeCompatibility::class),
            $persister,
            $this->createStub(OptionMappingPersister::class),
            $this->createStub(LoggerInterface::class)
        );

        self::assertSame(
            ['inserted' => 1, 'updated' => 0, 'deleted' => 1, 'unchanged' => 0],
            $saver->saveAdditions([['left' => ['code' => 'color'], 'right' => ['code' => 'color']]])
        );
    }
}
