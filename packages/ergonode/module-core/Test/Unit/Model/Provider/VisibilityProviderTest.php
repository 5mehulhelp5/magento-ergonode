<?php

declare(strict_types=1);

namespace Ergonode\Core\Test\Unit\Model\Provider;

use Ergonode\Core\Model\Provider\VisibilityProvider;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;

class VisibilityProviderTest extends TestCase
{
    public function testZeroCodeIsQueriedWhileEmptyCodesAreRemoved(): void
    {
        $conditions = [];
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();

        $select->method('where')->willReturnCallback(
            static function (string $condition, mixed $value) use (&$conditions, $select): Select {
                $conditions[$condition] = $value;
                return $select;
            }
        );
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('select')->willReturn($select);
        $connection->expects(self::once())->method('fetchAll')->with($select)->willReturn([
            ['identifier' => '0', 'is_active' => 0],
        ]);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $result = (new VisibilityProvider($resource))->getActiveMap(
            'category',
            'ergo',
            [' 0 ', '', '123', '001', '0', ' '],
            '7'
        );

        self::assertSame(['0', '123', '001'], $conditions['identifier IN (?)']);
        self::assertSame(['0' => false, '123' => true, '001' => true], $result);
    }
}
