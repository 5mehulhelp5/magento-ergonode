<?php

declare(strict_types=1);

namespace Ergonode\Language\Test\Unit\Model\Mapping;

use Ergonode\Language\Model\Mapping\LanguageStoreMappingRowsProvider;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;

class LanguageStoreMappingRowsProviderTest extends TestCase
{
    public function testReturnsCompleteRowsAndDraftsExactlyAsPersisted(): void
    {
        $connection = $this->createStub(AdapterInterface::class);
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('order')->willReturnSelf();
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturn([
            [
                'mapping_id' => '1',
                'store_id' => '0',
                'language_code' => ' en_GB ',
                'sort_order' => '0',
                'is_manual' => '1',
            ],
            ['mapping_id' => '2', 'store_id' => '4', 'language_code' => null, 'sort_order' => '1', 'is_manual' => '1'],
            [
                'mapping_id' => '3',
                'store_id' => null,
                'language_code' => 'de_DE',
                'sort_order' => '2',
                'is_manual' => '1',
            ],
        ]);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturn('ergonode_language_store_mapping');

        self::assertSame([
            ['mapping_id' => 1, 'store_id' => 0, 'language_code' => 'en_GB', 'sort_order' => 0, 'is_manual' => 1],
            ['mapping_id' => 2, 'store_id' => 4, 'language_code' => null, 'sort_order' => 1, 'is_manual' => 1],
            ['mapping_id' => 3, 'store_id' => null, 'language_code' => 'de_DE', 'sort_order' => 2, 'is_manual' => 1],
        ], (new LanguageStoreMappingRowsProvider($resource))->getRows());
    }
}
