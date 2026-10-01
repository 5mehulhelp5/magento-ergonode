<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Test\Unit\Model\Import;

use Ergonode\AttributeConsumer\Model\Provider\OptionSnapshotCache;
use Ergonode\AttributeConsumer\Model\Provider\ErgonodeAttributeProvider;
use Ergonode\AttributeConsumer\Model\Snapshot\AttributeSnapshotRemover;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class AttributeSnapshotRemoverTest extends TestCase
{
    public function testAtomicallyRemovesAttributeAndItsOptionCache(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::exactly(2))->method('delete')->willReturnCallback(
            static fn (string $table, array $where): int => match ($table) {
                'ergonode_attribute_option' => $where === ['attribute_code = ?' => 'color'] ? 3 : 0,
                'ergonode_attribute' => $where === ['code = ?' => 'color'] ? 1 : 0,
                default => 0,
            }
        );
        $connection->expects(self::once())->method('commit');
        $connection->expects(self::never())->method('rollBack');

        $this->remover($connection)->remove(' color ');
    }

    public function testRollsBackWhenAttributeIsMissing(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->method('delete')->willReturn(0);
        $connection->expects(self::never())->method('commit');
        $connection->expects(self::once())->method('rollBack');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Attribute "color" is not available in the local snapshot.');

        $this->remover($connection)->remove('color');
    }

    private function remover(AdapterInterface $connection): AttributeSnapshotRemover
    {
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        return new AttributeSnapshotRemover(
            $resource,
            new OptionSnapshotCache(),
            $this->createStub(ErgonodeAttributeProvider::class)
        );
    }
}
