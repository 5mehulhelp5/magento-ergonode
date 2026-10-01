<?php

declare(strict_types=1);

namespace Ergonode\Language\Test\Unit\Model\Storage;

use Ergonode\Language\Model\Storage\LanguageCodeStorage;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;

class LanguageCodeStorageTest extends TestCase
{
    public function testReadsCodesPersistedInMagento(): void
    {
        $select = $this->createMock(Select::class);
        $select->expects(self::once())->method('from')->with(
            'ergonode_language',
            ['language_code']
        )->willReturnSelf();
        $select->expects(self::once())->method('order')->with('language_code ASC')->willReturnSelf();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->expects(self::once())->method('fetchCol')->with($select)->willReturn(['de_DE', 'pl_PL']);

        self::assertSame(['de_DE', 'pl_PL'], $this->storage($connection)->getCodes());
    }

    public function testAtomicallyReplacesRemoteLanguageList(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('delete')->with('ergonode_language');
        $connection->expects(self::once())->method('insertArray')->with(
            'ergonode_language',
            ['language_code'],
            [['pl_PL'], ['en_GB']]
        );
        $connection->expects(self::once())->method('commit');
        $connection->expects(self::never())->method('rollBack');

        $this->storage($connection)->replace([' pl_PL ', 'en_GB', 'pl_PL', '']);
    }

    private function storage(AdapterInterface $connection): LanguageCodeStorage
    {
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->with('ergonode_language')->willReturn('ergonode_language');

        return new LanguageCodeStorage($resource);
    }
}
