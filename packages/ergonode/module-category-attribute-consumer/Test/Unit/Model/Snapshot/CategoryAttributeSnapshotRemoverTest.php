<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Unit\Model\Snapshot;

use Ergonode\CategoryAttributeConsumer\Model\Snapshot\CategoryAttributeSnapshotRemover;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class CategoryAttributeSnapshotRemoverTest extends TestCase
{
    public function testRemovesOnlySelectedCategoryAttributeRegistryRow(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('delete')->with(
            'ergonode_category_attribute',
            ['attribute_code = ?' => 'url_key']
        )->willReturn(1);

        $this->remover($connection)->remove(' url_key ');
    }

    public function testRejectsCategoryAttributeMissingFromSnapshot(): void
    {
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('delete')->willReturn(0);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Category attribute "url_key" is not available in the local snapshot.');

        $this->remover($connection)->remove('url_key');
    }

    private function remover(AdapterInterface $connection): CategoryAttributeSnapshotRemover
    {
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        return new CategoryAttributeSnapshotRemover($resource);
    }
}
