<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Unit\Model\Import;

use Ergonode\AttributeConsumer\Api\AttributeDefinitionSynchronizationInterface;
use Ergonode\CategoryAttributeConsumer\Api\CategoryAttributeCodeLoaderInterface;
use Ergonode\CategoryAttributeConsumer\Model\Import\CategoryAttributeRegistryRefresher;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use PHPUnit\Framework\TestCase;

class CategoryAttributeRegistryRefresherTest extends TestCase
{
    public function testRefreshesAttributeCacheAndPersistsCodesFromSharedLoader(): void
    {
        $attributeCacheRefresher = $this->createMock(AttributeDefinitionSynchronizationInterface::class);
        $attributeCacheRefresher->expects(self::once())->method('synchronize');
        $loader = $this->createMock(CategoryAttributeCodeLoaderInterface::class);
        $loader->expects(self::once())->method('load')->willReturn(['description', 'banner']);

        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('delete')->with('category_attribute_table');
        $connection->expects(self::once())->method('insertArray')->with(
            'category_attribute_table',
            ['attribute_code'],
            [['description'], ['banner']]
        );
        $connection->expects(self::once())->method('commit');

        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')
            ->with('ergonode_category_attribute')
            ->willReturn('category_attribute_table');

        $result = (new CategoryAttributeRegistryRefresher(
            $attributeCacheRefresher,
            $loader,
            $resourceConnection
        ))->refresh();

        self::assertSame([
            'imported' => 2,
        ], $result);
    }
}
