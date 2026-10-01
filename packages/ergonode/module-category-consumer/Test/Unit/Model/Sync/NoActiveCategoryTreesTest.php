<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Model\Sync;

use Ergonode\CategoryConsumer\Model\Sync\CategoryCacheInvalidator;

use Ergonode\Category\Model\CategoryTree\CategoryTreeQuery;

use Ergonode\Category\Model\Import\CategoryStreamPageReader;

use Ergonode\Category\Model\Import\CategoryTreeDownloadScope;

use Ergonode\CategoryConsumer\Api\CategoryCreationConfigurationProviderInterface;
use Ergonode\CategoryConsumer\Api\CategoryEntityLoaderInterface;

use Ergonode\CategoryConsumer\Api\CategoryEntitySynchronizerInterface;
use Ergonode\CategoryConsumer\Api\CategoryReconciliationServiceInterface;
use Ergonode\CategoryConsumer\Api\CategoryTreeReadinessProviderInterface;
use Ergonode\CategoryConsumer\Api\CategoryTreeSyncCursorResetterInterface;
use Ergonode\CategoryConsumer\Console\Command\SyncCategoryDataCommand;
use Ergonode\CategoryConsumer\Console\Command\SyncCategoryTreesCommand;
use Ergonode\CategoryConsumer\Cron\ImportCategories;
use Ergonode\CategoryConsumer\Cron\ImportCategoryData;
use Ergonode\CategoryConsumer\Model\Config\CategoryConfigProvider;
use Ergonode\CategoryConsumer\Model\Import\CategoryDataSyncCursorResetter;
use Ergonode\CategoryConsumer\Model\Import\CategoryEntityStreamImporter;
use Ergonode\CategoryConsumer\Model\Import\CategoryTreeStreamImporter;
use Ergonode\CategoryConsumer\Model\Provider\CategoryDataMappingProvider;
use Ergonode\CategoryConsumer\Model\Reconciliation\CategoryReconciliationErrorFormatter;
use Ergonode\CategoryConsumer\Model\Sync\CategoryDataSynchronizationProcess;
use Ergonode\CategoryConsumer\Model\Sync\CategorySourceAvailability;
use Ergonode\CategoryConsumer\Model\Sync\CategoryStreamEligibility;
use Ergonode\CategoryConsumer\Model\Sync\CategoryStructureSynchronizationProcess;
use Ergonode\CategoryConsumer\Model\Sync\CategorySynchronizationAction;
use Ergonode\Category\Model\Sync\CategorySynchronizationLock;
use Ergonode\CategoryConsumer\Model\Sync\CategorySynchronizationProgress;
use Ergonode\Core\Api\AutomaticSynchronizationInterface;
use Ergonode\Core\Model\Import\CursorStorage;
use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Tester\CommandTester;

class NoActiveCategoryTreesTest extends TestCase
{
    #[DataProvider('entryPoints')]
    public function testNoStreamCursorOrHistorySideEffects(string $entry, bool $reset): void
    {
        $config = $this->createStub(CategoryConfigProvider::class);
        foreach (['isEnabled', 'isDataSynchronizationEnabled', 'isCronEnabled', 'isDataCronEnabled'] as $method) {
            $config->method($method)->willReturn(true);
        }
        $trees = $this->createMock(CategoryTreeReadinessProviderInterface::class);
        $trees->expects(self::once())->method('getActiveTrees')->willReturn([]);
        $eligibility = new CategoryStreamEligibility(
            $config,
            $trees,
            $this->createStub(CategoryCreationConfigurationProviderInterface::class)
        );
        $cursor = $this->createMock(CursorStorage::class);
        foreach (['get', 'save', 'reset'] as $method) {
            $cursor->expects(self::never())->method($method);
        }
        $reader = $this->createMock(CategoryStreamPageReader::class);
        $reader->expects(self::never())->method('readAll');
        $languages = $this->createMock(LanguageStoreMappingProviderInterface::class);
        $languages->expects(self::never())->method('getLanguageStoreMap');
        $lock = $this->createMock(CategorySynchronizationLock::class);
        $lock->expects(self::never())->method('execute');
        $report = new ChangeReport(new Json());
        $tree = new CategoryStructureSynchronizationProcess(
            $eligibility,
            $lock,
            new CategoryTreeStreamImporter(
                $reader,
                $cursor,
                $this->createStub(CategoryTreeQuery::class),
                $this->createStub(CategoryReconciliationServiceInterface::class),
                $languages,
                $report,
                $this->createStub(CategorySynchronizationProgress::class),
                $this->createStub(CategorySourceAvailability::class)
            ),
            $report,
            new CategoryTreeDownloadScope()
        );
        $data = new CategoryDataSynchronizationProcess(
            $eligibility,
            $lock,
            new CategoryEntityStreamImporter(
                $reader,
                $cursor,
                $this->createStub(CategoryDataMappingProvider::class),
                $this->createStub(CategoryEntityLoaderInterface::class),
                $this->createStub(CategoryEntitySynchronizerInterface::class),
                $languages,
                $this->createStub(CategorySynchronizationProgress::class),
                $this->createStub(CategorySourceAvailability::class)
            ),
            $report,
            new CategoryTreeDownloadScope(),
            $this->createStub(CategoryCacheInvalidator::class)
        );
        $formatter = new CategoryReconciliationErrorFormatter();
        if (str_starts_with($entry, 'cli-')) {
            $command = $entry === 'cli-tree'
                ? new SyncCategoryTreesCommand($tree, $formatter)
                : new SyncCategoryDataCommand($data, $formatter);
            $tester = new CommandTester($command);
            self::assertSame(1, $tester->execute(['--reset-cursor' => $reset]));
            self::assertStringContainsString('Enable at least one category tree mapping', $tester->getDisplay());

            return;
        }
        if (str_starts_with($entry, 'cron-')) {
            $logger = $this->createMock(LoggerInterface::class);
            foreach (['info', 'warning', 'error'] as $method) {
                $logger->expects(self::never())->method($method);
            }
            $cron = $entry === 'cron-tree'
                ? new ImportCategories($this->automation(), $eligibility, $tree, $formatter, $logger)
                : new ImportCategoryData($this->automation(), $eligibility, $data, $formatter, $logger);
            $cron->execute();

            return;
        }
        $treeResetter = $this->createMock(CategoryTreeSyncCursorResetterInterface::class);
        $treeResetter->expects(self::never())->method('reset');
        $dataResetter = $this->createMock(CategoryDataSyncCursorResetter::class);
        $dataResetter->expects(self::never())->method('reset');
        $action = new CategorySynchronizationAction(
            $tree,
            $data,
            $treeResetter,
            $dataResetter,
            $config,
            $this->createStub(CategorySynchronizationProgress::class),
            $this->createStub(CursorStorage::class)
        );
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Enable at least one category tree mapping before synchronizing.');
        $action->execute(substr($entry, 6), $reset ? 'reset-cursor-and-sync' : 'sync');
    }

    /** @return iterable<string, array{string, bool}> */
    public static function entryPoints(): iterable
    {
        foreach (['cli-tree', 'cli-data', 'admin-tree', 'admin-data', 'admin-all'] as $entry) {
            yield $entry => [$entry, false];
            yield $entry . ' reset and sync' => [$entry, true];
        }
        yield 'cron-tree' => ['cron-tree', false];
        yield 'cron-data' => ['cron-data', false];
    }
    private function automation(): AutomaticSynchronizationInterface
    {
        $policy = $this->createStub(AutomaticSynchronizationInterface::class);
        $policy->method('isAllowed')->willReturn(true);
        return $policy;
    }
}
