<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttribute\Test\Unit\Model;

use Ergonode\ProductAttribute\Api\MappingReaderInterface;
use Ergonode\TemplateAttribute\Model\StructureProvider;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class StructureProviderTest extends TestCase
{
    public function testRejectsAStalePairBeforeReadingOrWritingStructure(): void
    {
        $select = $this->getStubBuilder(Select::class)->disableOriginalConstructor()
            ->onlyMethods(['from', 'joinInner', 'where'])->getStub();
        foreach (['from', 'joinInner', 'where'] as $method) {
            $select->method($method)->willReturnSelf();
        }
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchRow')->willReturn(false);
        $connection->expects(self::never())->method('fetchAll');
        $connection->expects(self::never())->method('update');
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $this->expectException(LocalizedException::class);
        (new StructureProvider($resource, $this->createStub(MappingReaderInterface::class)))->get('stale-template', 4);
    }
}
