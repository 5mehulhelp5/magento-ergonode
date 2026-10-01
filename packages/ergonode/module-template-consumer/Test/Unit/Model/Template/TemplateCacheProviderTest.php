<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Test\Unit\Model\Template;

use Ergonode\TemplateConsumer\Model\Mapping\TemplateAttributeSetMappingResource;
use Ergonode\TemplateConsumer\Model\Sync\AttributeSetResource;
use Ergonode\TemplateConsumer\Model\Template\TemplateCacheProvider;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TemplateCacheProviderTest extends TestCase
{
    public function testClearsStaleAttributeSetInSingleTransaction(): void
    {
        $select = $this->createSelect();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())->method('select')->willReturn($select);
        $connection->expects($this->once())
            ->method('fetchRow')
            ->with($select)
            ->willReturn(['entity_id' => '7', 'attribute_set_id' => '12']);
        $updates = [];
        $connection->expects($this->once())
            ->method('update')
            ->willReturnCallback(static function (string $table, array $data, array $where) use (&$updates): int {
                $updates[] = [$table, $data, $where];

                return 1;
            });
        $connection->expects($this->once())->method('commit');
        $connection->expects($this->never())->method('rollBack');

        $attributeSetResource = $this->createMock(AttributeSetResource::class);
        $attributeSetResource->expects($this->once())
            ->method('productAttributeSetExists')
            ->with(12)
            ->willReturn(false);

        $provider = $this->provider($connection, $attributeSetResource);
        self::assertTrue($provider->clearStaleAttributeSetMapping('code', 12));
        self::assertSame(
            [
                [
                    'ergonode_template',
                    ['attribute_set_id' => null],
                    ['entity_id = ?' => 7, 'attribute_set_id = ?' => 12],
                ],
            ],
            $updates
        );
    }

    public function testKeepsMappingWhenProductAttributeSetStillExists(): void
    {
        $select = $this->createSelect();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('beginTransaction');
        $connection->method('select')->willReturn($select);
        $connection->method('fetchRow')->willReturn(['entity_id' => '7', 'attribute_set_id' => '12']);
        $connection->expects($this->never())->method('update');
        $connection->expects($this->once())->method('commit');
        $connection->expects($this->never())->method('rollBack');

        $attributeSetResource = $this->createStub(AttributeSetResource::class);
        $attributeSetResource->method('productAttributeSetExists')->willReturn(true);

        self::assertFalse(
            $this->provider($connection, $attributeSetResource)->clearStaleAttributeSetMapping('code', 12)
        );
    }

    public function testRollsBackWhenClearingMappingFails(): void
    {
        $select = $this->createSelect();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('beginTransaction');
        $connection->method('select')->willReturn($select);
        $connection->method('fetchRow')->willReturn(['entity_id' => '7', 'attribute_set_id' => '12']);
        $connection->method('update')->willThrowException(new RuntimeException('Update failed.'));
        $connection->expects($this->never())->method('commit');
        $connection->expects($this->once())->method('rollBack');

        $attributeSetResource = $this->createStub(AttributeSetResource::class);
        $attributeSetResource->method('productAttributeSetExists')->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Update failed.');

        $this->provider($connection, $attributeSetResource)->clearStaleAttributeSetMapping('code', 12);
    }

    private function createSelect(): Select
    {
        $select = $this->getMockBuilder(Select::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['from', 'where', 'limit', 'forUpdate'])
            ->getMock();
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('limit')->willReturnSelf();
        $select->method('forUpdate')->willReturnSelf();

        return $select;
    }

    private function provider(
        AdapterInterface $connection,
        AttributeSetResource $attributeSetResource
    ): TemplateCacheProvider {
        $resourceConnection = $this->createStub(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')->willReturnArgument(0);

        return new TemplateCacheProvider(
            $resourceConnection,
            $attributeSetResource,
            $this->createStub(TemplateAttributeSetMappingResource::class)
        );
    }
}
