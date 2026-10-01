<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Model\Sync;

use Ergonode\CategoryConsumer\Api\CategoryDataWorkProviderInterface;
use Ergonode\CategoryConsumer\Api\CategoryEntityLoaderInterface;
use Ergonode\CategoryConsumer\Api\CategoryEntitySynchronizerInterface;
use Ergonode\CategoryConsumer\Model\Config\CategoryConfigProvider;
use Ergonode\CategoryConsumer\Model\Sync\MappedCategoryAttributeSynchronizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MappedCategoryAttributeSynchronizerTest extends TestCase
{
    public function testLoadsExactEntityAndSynchronizesOnlyRequestedCategory(): void
    {
        $entity = [
            'code' => 'chairs',
            'labels' => ['en_US' => 'Chairs'],
            'attributes' => [['code' => 'description', 'values' => ['en_US' => 'Text']]],
            'hash' => 'hash',
            'raw' => [],
        ];
        $config = $this->createStub(CategoryConfigProvider::class);
        $config->method('isDataSynchronizationEnabled')->willReturn(true);
        $loader = $this->createMock(CategoryEntityLoaderInterface::class);
        $loader->expects(self::once())->method('load')->with('chairs')->willReturn($entity);
        $synchronizer = $this->createMock(CategoryEntitySynchronizerInterface::class);
        $synchronizer->expects(self::once())->method('synchronize')->with([[
            'category_id' => 42,
            'entity' => $entity,
        ]])->willReturn([
            'snapshots' => 1,
            'snapshot_statuses' => ['chairs' => 'updated'],
            'attributes' => 2,
            'changed_category_ids' => [42],
        ]);

        self::assertSame(
            ['snapshot' => 'updated', 'attributes' => 2],
            (new MappedCategoryAttributeSynchronizer($config, $loader, $synchronizer))
                ->synchronize('chairs', 42)
        );
    }

    public static function synchronizationWork(): array
    {
        return [
            'disabled' => [false, true, false],
            'unused' => [true, false, false],
            'configured' => [true, true, true],
            'legacy' => [true, null, true],
        ];
    }

    #[DataProvider('synchronizationWork')]
    public function testReportsAutomaticWorkWithoutLoadingEntities(bool $enabled, ?bool $work, bool $expected): void
    {
        $config = $this->createStub(CategoryConfigProvider::class);
        $config->method('isDataSynchronizationEnabled')->willReturn($enabled);
        $loader = $this->createMock(CategoryEntityLoaderInterface::class);
        $loader->expects(self::never())->method('load');
        $entitySync = $work === null
            ? $this->createStub(CategoryEntitySynchronizerInterface::class)
            : $this->createMockForIntersectionOfInterfaces([
                CategoryEntitySynchronizerInterface::class, CategoryDataWorkProviderInterface::class,
            ]);
        if ($work !== null) {
            $entitySync->expects($enabled ? self::once() : self::never())
                ->method('hasWork')->willReturn($work);
        }
        self::assertSame(
            $expected,
            (new MappedCategoryAttributeSynchronizer($config, $loader, $entitySync))->hasWork()
        );
    }

    public function testExplicitRefreshStillLoadsSnapshotWhenAutomaticUpdatesHaveNoWork(): void
    {
        $config = $this->createStub(CategoryConfigProvider::class);
        $config->method('isDataSynchronizationEnabled')->willReturn(true);
        $loader = $this->createMock(CategoryEntityLoaderInterface::class);
        $loader->expects(self::once())->method('load')->with('chairs')->willReturn(['code' => 'chairs']);
        $entitySync = $this->createMockForIntersectionOfInterfaces([
            CategoryEntitySynchronizerInterface::class, CategoryDataWorkProviderInterface::class,
        ]);
        $entitySync->expects(self::once())->method('hasWork')->willReturn(false);
        $entitySync->expects(self::once())->method('synchronize')->willReturn([
            'snapshot_statuses' => ['chairs' => 'updated'], 'attributes' => 0,
        ]);
        $mapped = new MappedCategoryAttributeSynchronizer($config, $loader, $entitySync);
        self::assertFalse($mapped->hasWork());
        self::assertSame(['snapshot' => 'updated', 'attributes' => 0], $mapped->synchronize('chairs', 42));
    }

    public function testDisabledAttributeSynchronizationDoesNotLoadEntity(): void
    {
        $config = $this->createStub(CategoryConfigProvider::class);
        $config->method('isDataSynchronizationEnabled')->willReturn(false);
        $loader = $this->createMock(CategoryEntityLoaderInterface::class);
        $loader->expects(self::never())->method('load');

        self::assertSame(
            ['snapshot' => 'unchanged', 'attributes' => 0],
            (new MappedCategoryAttributeSynchronizer(
                $config,
                $loader,
                $this->createStub(CategoryEntitySynchronizerInterface::class)
            ))->synchronize('chairs', 42)
        );
    }
}
