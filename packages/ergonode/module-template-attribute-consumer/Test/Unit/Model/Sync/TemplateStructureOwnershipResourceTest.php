<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Test\Unit\Model\Sync;

use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateStructureOwnershipResource;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;

class TemplateStructureOwnershipResourceTest extends TestCase
{
    public function testSavesGroupOwnershipByStableIdentifiers(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())
            ->method('insertOnDuplicate')
            ->with(
                'prefixed_group_ownership',
                [[
                    'template_code' => 'product',
                    'attribute_set_id' => 12,
                    'section_code' => 'general',
                    'attribute_group_id' => 18,
                ]],
                ['template_code', 'attribute_set_id', 'section_code', 'attribute_group_id']
            );
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->expects($this->once())
            ->method('getTableName')
            ->with('ergonode_template_group_ownership')
            ->willReturn('prefixed_group_ownership');

        (new TemplateStructureOwnershipResource($resourceConnection))->saveGroupOwnership(
            'product',
            12,
            'general',
            18
        );
    }

    public function testSavesAttributeOwnershipByStableIdentifiers(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())
            ->method('insertOnDuplicate')
            ->with(
                'prefixed_attribute_ownership',
                [[
                    'template_code' => 'product',
                    'attribute_set_id' => 12,
                    'ergonode_attribute_code' => 'color',
                    'magento_attribute_id' => 41,
                    'attribute_group_id' => 18,
                    'entity_attribute_id' => 77,
                ]],
                [
                    'template_code',
                    'attribute_set_id',
                    'ergonode_attribute_code',
                    'magento_attribute_id',
                    'attribute_group_id',
                    'entity_attribute_id',
                ]
            );
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->expects($this->once())
            ->method('getTableName')
            ->with('ergonode_template_attribute_ownership')
            ->willReturn('prefixed_attribute_ownership');

        (new TemplateStructureOwnershipResource($resourceConnection))->saveAttributeOwnership(
            'product',
            12,
            'color',
            41,
            18,
            77
        );
    }

    public function testFindsTheUniqueOwnerOfAnAttributeGroupCode(): void
    {
        $select = $this->getMockBuilder(Select::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['from', 'joinLeft', 'where', 'limit'])
            ->getMock();
        $select->method('from')->willReturnSelf();
        $select->expects($this->once())->method('joinLeft')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('limit')->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('select')->willReturn($select);
        $connection->expects($this->once())
            ->method('fetchRow')
            ->with($select)
            ->willReturn([
                'attribute_group_id' => '18',
                'template_code' => 'product',
                'section_code' => 'general',
            ]);
        $resourceConnection = $this->createStub(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')->willReturnCallback(
            static fn (string $table): string => 'prefixed_' . $table
        );

        self::assertSame(
            [
                'attribute_group_id' => 18,
                'template_code' => 'product',
                'section_code' => 'general',
            ],
            (new TemplateStructureOwnershipResource($resourceConnection))->findGroupCodeOwner(
                12,
                'ergonode_general'
            )
        );
    }

    public function testFindsGroupOwnerByAttributeGroupId(): void
    {
        $select = $this->getMockBuilder(Select::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['from', 'where', 'limit'])
            ->getMock();
        $select->method('from')->willReturnSelf();
        $select->expects($this->once())
            ->method('where')
            ->with('attribute_group_id = ?', 18)
            ->willReturnSelf();
        $select->method('limit')->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('select')->willReturn($select);
        $connection->expects($this->once())
            ->method('fetchRow')
            ->with($select)
            ->willReturn([
                'template_code' => 'product',
                'attribute_set_id' => '12',
                'section_code' => 'general',
            ]);
        $resourceConnection = $this->createStub(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')->willReturn('prefixed_group_ownership');

        self::assertSame(
            [
                'template_code' => 'product',
                'attribute_set_id' => 12,
                'section_code' => 'general',
            ],
            (new TemplateStructureOwnershipResource($resourceConnection))->findGroupOwnerById(18)
        );
    }
}
