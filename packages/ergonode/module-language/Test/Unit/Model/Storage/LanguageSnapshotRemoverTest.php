<?php

declare(strict_types=1);

namespace Ergonode\Language\Test\Unit\Model\Storage;

use Ergonode\Language\Model\Storage\LanguageSnapshotRemover;
use Ergonode\Language\Model\Mapping\MappingLock;
use Ergonode\Language\Model\Mapping\MappingCache;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class LanguageSnapshotRemoverTest extends TestCase
{
    public function testRemovesOnlySelectedLanguageCode(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('delete')->with(
            'ergonode_language',
            ['language_code = ?' => 'pl_PL']
        )->willReturn(1);

        $this->remover($connection)->remove(' pl_PL ');
    }

    public function testRejectsLanguageMissingFromSnapshot(): void
    {
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('delete')->willReturn(0);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Language "pl_PL" is not available in the local snapshot.');

        $this->remover($connection)->remove('pl_PL');
    }

    private function remover(AdapterInterface $connection): LanguageSnapshotRemover
    {
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $manager = $this->createStub(LockManagerInterface::class);
        $manager->method('lock')->willReturn(true);
        return new LanguageSnapshotRemover(
            $resource,
            new MappingLock($manager),
            $this->createStub(MappingCache::class)
        );
    }
}
