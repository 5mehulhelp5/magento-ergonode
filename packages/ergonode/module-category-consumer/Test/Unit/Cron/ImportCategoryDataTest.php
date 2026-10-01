<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Cron;

use Ergonode\CategoryConsumer\Api\CategoryCreationConfigurationProviderInterface;

use Ergonode\CategoryConsumer\Api\CategoryDataSynchronizationProcessInterface;
use Ergonode\CategoryConsumer\Api\CategoryTreeReadinessProviderInterface;
use Ergonode\CategoryConsumer\Cron\ImportCategoryData;
use Ergonode\CategoryConsumer\Model\Config\CategoryConfigProvider;
use Ergonode\CategoryConsumer\Model\Reconciliation\CategoryReconciliationErrorFormatter;
use Ergonode\CategoryConsumer\Model\Sync\CategoryStreamEligibility;
use Ergonode\Core\Api\AutomaticSynchronizationInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ImportCategoryDataTest extends TestCase
{
    public function testDelegatesEnabledCronToTheSharedAttributeProcess(): void
    {
        $config = $this->createStub(CategoryConfigProvider::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('isDataCronEnabled')->willReturn(true);
        $config->method('isDataSynchronizationEnabled')->willReturn(true);
        $stats = [
            'events' => 2,
            'fetched' => 1,
            'snapshots' => 1,
            'attributes' => 3,
            'cursor' => 'cursor',
        ];
        $process = $this->createMock(CategoryDataSynchronizationProcessInterface::class);
        $process->expects(self::once())->method('execute')->willReturn($stats);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with('Ergonode category data stream processed.', $stats);

        (new ImportCategoryData(
            $this->automation(),
            $this->eligibility($config),
            $process,
            new CategoryReconciliationErrorFormatter(),
            $logger
        ))->execute();
    }

    public function testDoesNotAcquireProcessWhenAttributeSynchronizationIsDisabled(): void
    {
        $config = $this->createStub(CategoryConfigProvider::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('isDataCronEnabled')->willReturn(true);
        $config->method('isDataSynchronizationEnabled')->willReturn(false);
        $process = $this->createMock(CategoryDataSynchronizationProcessInterface::class);
        $process->expects(self::never())->method('execute');

        (new ImportCategoryData(
            $this->automation(),
            $this->eligibility($config),
            $process,
            new CategoryReconciliationErrorFormatter(),
            $this->createStub(LoggerInterface::class)
        ))->execute();
    }

    public function testDoesNotAcquireProcessWhenCronIsDisabled(): void
    {
        $config = $this->createStub(CategoryConfigProvider::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('isDataCronEnabled')->willReturn(false);
        $config->method('isDataSynchronizationEnabled')->willReturn(true);
        $process = $this->createMock(CategoryDataSynchronizationProcessInterface::class);
        $process->expects(self::never())->method('execute');

        (new ImportCategoryData(
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
