<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Model\Sync;

use Ergonode\Category\Model\Import\CategoryTreeDownloadScope;

use Ergonode\CategoryConsumer\Api\CategoryCreationConfigurationProviderInterface;
use Ergonode\CategoryConsumer\Api\CategoryTreeReadinessProviderInterface;
use Ergonode\CategoryConsumer\Model\Config\CategoryConfigProvider;
use Ergonode\CategoryConsumer\Model\Import\CategoryEntityStreamImporter;
use Ergonode\CategoryConsumer\Model\Import\CategoryTreeStreamImporter;
use Ergonode\CategoryConsumer\Model\Sync\CategoryDataSynchronizationProcess;
use Ergonode\CategoryConsumer\Model\Sync\CategoryStreamEligibility;
use Ergonode\CategoryConsumer\Model\Sync\CategoryStructureSynchronizationProcess;
use Ergonode\Category\Model\Sync\CategorySynchronizationLock;
use Ergonode\Core\Model\Report\ChangeReport;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use Ergonode\CategoryConsumer\Model\Sync\CategoryCacheInvalidator;
use Ergonode\Category\Model\Provider\MagentoCategoryProvider;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Event\ManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class CategorySynchronizationProcessTest extends TestCase
{
    public function testStructureProcessRunsOnlyTreeStreamUnderSharedLock(): void
    {
        $config = $this->createStub(CategoryConfigProvider::class);
        $config->method('isEnabled')->willReturn(true);
        $stats = ['events' => 1, 'trees' => 1, 'conflicts' => 0, 'cursor' => 'tree'];
        $importer = $this->createMock(CategoryTreeStreamImporter::class);
        $importer->expects(self::once())->method('execute')->willReturn($stats);

        self::assertSame(
            $stats,
            (new CategoryStructureSynchronizationProcess(
                $this->eligibility($config),
                $this->lock(),
                $importer,
                new ChangeReport(new Json()),
                new CategoryTreeDownloadScope()
            ))->execute()
        );
    }

    public function testAttributeProcessRunsOnlyEntityStreamUnderSharedLock(): void
    {
        $config = $this->createStub(CategoryConfigProvider::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('isDataSynchronizationEnabled')->willReturn(true);
        $stats = [
            'events' => 1,
            'fetched' => 1,
            'snapshots' => 1,
            'attributes' => 2,
            'cursor' => 'entity',
        ];
        $importer = $this->createMock(CategoryEntityStreamImporter::class);
        $importer->expects(self::once())->method('execute')->willReturn($stats);

        self::assertSame(
            $stats,
            (new CategoryDataSynchronizationProcess(
                $this->eligibility($config),
                $this->lock(),
                $importer,
                new ChangeReport(new Json()),
                new CategoryTreeDownloadScope(),
                $this->invalidator()
            ))->execute()
        );
    }

    public function testAttributeProcessRejectsDisabledAttributeSynchronization(): void
    {
        $config = $this->createStub(CategoryConfigProvider::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('isDataSynchronizationEnabled')->willReturn(false);
        $importer = $this->createMock(CategoryEntityStreamImporter::class);
        $importer->expects(self::never())->method('execute');

        $this->expectException(LocalizedException::class);
        (new CategoryDataSynchronizationProcess(
            $this->eligibility($config),
            $this->lock(),
            $importer,
            new ChangeReport(new Json()),
            new CategoryTreeDownloadScope(),
            $this->invalidator()
        ))->execute();
    }

    #[DataProvider('streamOutcomes')]
    public function testDataProcessFlushesMultipleBatchesOnceIncludingPartialFailure(bool $fails): void
    {
        $config = $this->createStub(CategoryConfigProvider::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('isDataSynchronizationEnabled')->willReturn(true);
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())->method('clean')->with(['cat_c', 'cat_c_10', 'cat_c_11']);
        $types = $this->createMock(TypeListInterface::class);
        $types->expects(self::exactly(2))->method('cleanType');
        $provider = $this->createMock(MagentoCategoryProvider::class);
        $provider->expects(self::once())->method('clearCache');
        $invalidator = new CategoryCacheInvalidator(
            $cache,
            $types,
            $this->createStub(ManagerInterface::class),
            $provider
        );
        $stats = ['events' => 2, 'fetched' => 2, 'snapshots' => 2, 'attributes' => 2, 'cursor' => 'next'];
        $importer = $this->createMock(CategoryEntityStreamImporter::class);
        $importer->expects(self::once())->method('execute')->willReturnCallback(
            static function () use ($invalidator, $fails, $stats): array {
                $invalidator->invalidateCategories([10]);
                $invalidator->invalidateCategories([11]);
                if ($fails) {
                    throw new RuntimeException('Later batch failed');
                }
                return $stats;
            }
        );
        $process = new CategoryDataSynchronizationProcess(
            $this->eligibility($config),
            $this->lock(),
            $importer,
            new ChangeReport(new Json()),
            new CategoryTreeDownloadScope(),
            $invalidator
        );
        if ($fails) {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Later batch failed');
        }
        self::assertSame($stats, $process->execute());
    }

    public static function streamOutcomes(): array
    {
        return ['success' => [false], 'partial failure' => [true]];
    }

    private function invalidator(): CategoryCacheInvalidator
    {
        $invalidator = $this->createStub(CategoryCacheInvalidator::class);
        $invalidator->method('defer')->willReturnCallback(static fn (callable $operation): mixed => $operation());
        return $invalidator;
    }

    private function eligibility(CategoryConfigProvider $config): CategoryStreamEligibility
    {
        $trees = $this->createStub(CategoryTreeReadinessProviderInterface::class);
        $trees->method('getActiveTrees')->willReturn([[
            'category_tree_id' => 1,
            'tree_code' => 'main',
            'root_category_id' => 2,
            'root_exists' => true,
        ]]);

        return new CategoryStreamEligibility(
            $config,
            $trees,
            $this->createStub(CategoryCreationConfigurationProviderInterface::class)
        );
    }

    private function lock(): CategorySynchronizationLock
    {
        $manager = $this->createStub(LockManagerInterface::class);
        $manager->method('lock')->willReturn(true);

        return new CategorySynchronizationLock($manager);
    }
}
