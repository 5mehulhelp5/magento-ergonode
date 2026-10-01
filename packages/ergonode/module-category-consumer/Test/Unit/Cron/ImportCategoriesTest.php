<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Cron;

use Ergonode\CategoryConsumer\Api\CategoryCreationConfigurationProviderInterface;

use Ergonode\CategoryConsumer\Api\CategoryStructureSynchronizationProcessInterface;
use Ergonode\CategoryConsumer\Api\CategoryTreeReadinessProviderInterface;
use Ergonode\CategoryConsumer\Cron\ImportCategories;
use Ergonode\CategoryConsumer\Model\Config\CategoryConfigProvider;
use Ergonode\CategoryConsumer\Model\Reconciliation\CategoryReconciliationErrorFormatter;
use Ergonode\CategoryConsumer\Model\Sync\CategoryStreamEligibility;
use Ergonode\Core\Api\AutomaticSynchronizationInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ImportCategoriesTest extends TestCase
{
    public function testDelegatesEnabledCronToTheSharedStructureProcess(): void
    {
        $config = $this->createStub(CategoryConfigProvider::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('isCronEnabled')->willReturn(true);
        $stats = ['events' => 2, 'trees' => 1, 'conflicts' => 0, 'cursor' => 'cursor'];
        $process = $this->createMock(CategoryStructureSynchronizationProcessInterface::class);
        $process->expects(self::once())->method('execute')->willReturn($stats);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with('Ergonode category structure stream processed.', $stats);

        (new ImportCategories(
            $this->automation(),
            $this->eligibility($config),
            $process,
            new CategoryReconciliationErrorFormatter(),
            $logger
        ))->execute();
    }

    public function testLogsCompletedWithConflictsAsWarning(): void
    {
        $config = $this->createStub(CategoryConfigProvider::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('isCronEnabled')->willReturn(true);
        $stats = ['events' => 2, 'trees' => 1, 'conflicts' => 1, 'cursor' => 'cursor'];
        $process = $this->createStub(CategoryStructureSynchronizationProcessInterface::class);
        $process->method('execute')->willReturn($stats);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with('Ergonode category structure stream completed with conflicts.', $stats);

        (new ImportCategories(
            $this->automation(),
            $this->eligibility($config),
            $process,
            new CategoryReconciliationErrorFormatter(),
            $logger
        ))->execute();
    }

    public function testDoesNotAcquireProcessWhenCronIsDisabled(): void
    {
        $config = $this->createStub(CategoryConfigProvider::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('isCronEnabled')->willReturn(false);
        $process = $this->createMock(CategoryStructureSynchronizationProcessInterface::class);
        $process->expects(self::never())->method('execute');

        (new ImportCategories(
            $this->automation(),
            $this->eligibility($config),
            $process,
            new CategoryReconciliationErrorFormatter(),
            $this->createStub(LoggerInterface::class)
        ))->execute();
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
    private function automation(): AutomaticSynchronizationInterface
    {
        $policy = $this->createStub(AutomaticSynchronizationInterface::class);
        $policy->method('isAllowed')->willReturn(true);
        return $policy;
    }
}
