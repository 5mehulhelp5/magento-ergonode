<?php

declare(strict_types=1);

namespace Ergonode\Category\Test\Unit\Model\Provider;

use Ergonode\Category\Model\Provider\CategoryMappingQuery;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;

class CategoryMappingQueryTest extends TestCase
{
    public function testZeroCodeIsQueriedWhileEmptyCodesAreRemoved(): void
    {
        $conditions = [];
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('joinInner')->willReturnSelf();
        $select->method('where')->willReturnCallback(
            static function (string $condition, mixed $value) use (&$conditions, $select): Select {
                $conditions[$condition] = $value;
                return $select;
            }
        );
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('select')->willReturn($select);
        $connection->expects(self::once())->method('fetchAll')->with($select)->willReturn([[
            'ergonode_category_code' => '0',
            'category_tree_id' => 7,
            'magento_category_id' => 10,
        ]]);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $result = (new CategoryMappingQuery($resource))->getValidMappingsByCodes([' 0 ', '', '123', '001', '0', ' ']);

        self::assertSame(['0', '123', '001'], $conditions['mapping.ergonode_category_code IN (?)']);
        self::assertSame(['0' => [['category_tree_id' => 7, 'magento_category_id' => 10]]], $result);
    }
}
