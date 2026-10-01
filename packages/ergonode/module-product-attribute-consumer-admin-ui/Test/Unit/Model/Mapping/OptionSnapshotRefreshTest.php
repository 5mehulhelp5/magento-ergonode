<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumerAdminUi\Test\Unit\Model\Mapping;

use Ergonode\AttributeConsumer\Api\AttributeCacheRefresherInterface;
use Ergonode\ProductAttributeConsumerAdminUi\Model\Mapping\OptionSnapshotRefresh;
use Ergonode\ProductAttributeAdminUi\Model\Mapping\AttributeMappingProvider;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\TestCase;

class OptionSnapshotRefreshTest extends TestCase
{
    public function testRefreshesOnlyTheSelectedErgonodeOptionSnapshot(): void
    {
        $mappings = $this->createMock(AttributeMappingProvider::class);
        $mappings->expects(self::once())->method('getMappingRow')->with(17)->willReturn([
            'ergonode_attribute_code' => 'color',
            'magento_attribute_code' => 'color',
        ]);
        $cache = $this->createMock(AttributeCacheRefresherInterface::class);
        $cache->expects(self::once())->method('refreshOptions')->with('color');
        $lock = $this->createMock(LockManagerInterface::class);
        $lock->expects(self::once())
            ->method('lock')
            ->with('ergonode_option_synchronization', 0)
            ->willReturn(true);
        $lock->expects(self::once())->method('unlock')->with('ergonode_option_synchronization');

        self::assertSame(
            ['ergonode_attribute_code' => 'color'],
            (new OptionSnapshotRefresh(
                $mappings,
                $cache,
                $lock
            ))->execute(17)
        );
    }

    public function testRejectsMissingAttributeMappingWithoutRefreshing(): void
    {
        $mappings = $this->createStub(AttributeMappingProvider::class);
        $mappings->method('getMappingRow')->willReturn(null);
        $cache = $this->createMock(AttributeCacheRefresherInterface::class);
        $cache->expects(self::never())->method('refreshOptions');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Options require a saved attribute mapping.');

        (new OptionSnapshotRefresh(
            $mappings,
            $cache,
            $this->createStub(LockManagerInterface::class)
        ))->execute(99);
    }
}
