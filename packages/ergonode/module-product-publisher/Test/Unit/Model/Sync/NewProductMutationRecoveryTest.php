<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\Unit\Model\Sync;

use Ergonode\ProductPublisher\Model\GraphQl\ProductMutationFactory;
use Ergonode\ProductPublisher\Model\Sync\NewProductMutationRecovery;
use Ergonode\ProductPublisher\Model\Sync\NewProductVisibility;
use Ergonode\ProductPublisher\Model\Sync\ProductMutationFailureClassifier;
use Ergonode\Publisher\Api\Data\MutationResultInterface;
use Ergonode\Publisher\Api\MutationBatchPlannerInterface;
use Ergonode\Publisher\Api\MutationExecutorInterface;
use Ergonode\Publisher\Model\Data\MutationBatch;
use Ergonode\Publisher\Model\Data\MutationResult;
use Ergonode\Publisher\Model\Data\SynchronizationResult;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class NewProductMutationRecoveryTest extends TestCase
{
    public function testVisibleProductsResumeWhileOnlyRemainingProductsArePolled(): void
    {
        $first = $this->mutationResult('1000000146');
        $second = $this->mutationResult('1000000147');
        $success = $this->mutationResult('1000000148', MutationResultInterface::STATUS_SUCCESS);
        $events = [];
        $visibility = $this->createMock(NewProductVisibility::class);
        $visibility->expects(self::exactly(3))->method('load')->willReturnCallback(
            static function (array $skus, int $delay) use (&$events): array {
                $events[] = ['read', $skus, $delay];
                return match ($delay) {
                    0 => [],
                    100 => ['1000000147'],
                    200 => ['1000000146'],
                };
            }
        );
        $executor = $this->createMock(MutationExecutorInterface::class);
        $executor->expects(self::exactly(2))->method('execute')->willReturnCallback(
            static function (MutationBatch $batch) use (&$events): SynchronizationResult {
                $operation = array_values($batch->getOperationsByAlias())[0];
                $events[] = ['write', $operation->getMetadata()['entity_sku']];
                return new SynchronizationResult([
                    new MutationResult(MutationResultInterface::STATUS_SUCCESS, 'retry_0', $operation),
                ]);
            }
        );

        $results = $this->recovery($visibility, $executor)->recover(
            [$first, $second, $success],
            ['1000000146', '1000000147', '1000000148']
        );

        self::assertSame([
            ['read', ['1000000146', '1000000147'], 0],
            ['read', ['1000000146', '1000000147'], 100],
            ['write', '1000000147'],
            ['read', ['1000000146'], 200],
            ['write', '1000000146'],
        ], $events);
        self::assertSame($success, $results[2]);
        self::assertSame($first->getAlias(), $results[0]->getAlias());
        self::assertSame($first->getOperation(), $results[0]->getOperation());
        self::assertSame(2, $results[0]->getAttempts());
        self::assertSame(MutationResultInterface::STATUS_SUCCESS, $results[1]->getStatus());
    }

    public function testSuccessfulExistingAndAmbiguousWritesDoNotTriggerQueriesOrReplay(): void
    {
        $visibility = $this->createMock(NewProductVisibility::class);
        $visibility->expects(self::never())->method('load');
        $executor = $this->createMock(MutationExecutorInterface::class);
        $executor->expects(self::never())->method('execute');
        $results = [
            $this->mutationResult('existing'),
            $this->mutationResult('new', MutationResultInterface::STATUS_SUCCESS),
            $this->mutationResult('new', MutationResultInterface::STATUS_UNRESOLVED),
            $this->mutationResult('new', MutationResultInterface::STATUS_PERMANENT_FAILURE),
            $this->mutationResult('new', MutationResultInterface::STATUS_TRANSIENT_FAILURE),
        ];

        self::assertSame($results, $this->recovery($visibility, $executor)->recover($results, ['new']));
    }

    public function testPersistentValidationFailureIsReturnedAfterOneReplay(): void
    {
        $original = $this->mutationResult('new');
        $visibility = $this->createMock(NewProductVisibility::class);
        $visibility->expects(self::once())->method('load')->with(['new'], 0)->willReturn(['new']);
        $executor = $this->createMock(MutationExecutorInterface::class);
        $executor->expects(self::once())->method('execute')->willReturn(new SynchronizationResult([
            new MutationResult(
                MutationResultInterface::STATUS_VALIDATION_FAILURE,
                'retry_0',
                $original->getOperation(),
                errors: [['message' => 'Value is not allowed.']]
            ),
        ]));

        $result = $this->recovery($visibility, $executor)->recover([$original], ['new'])[0];

        self::assertSame(MutationResultInterface::STATUS_VALIDATION_FAILURE, $result->getStatus());
        self::assertSame([['message' => 'Value is not allowed.']], $result->getErrors());
        self::assertSame(2, $result->getAttempts());
    }

    public function testVisibilityTimeoutPreservesOriginalErrorAndStopsAtConfiguredLimit(): void
    {
        $delays = [];
        $visibility = $this->createMock(NewProductVisibility::class);
        $visibility->expects(self::exactly(6))->method('load')->willReturnCallback(
            static function (array $skus, int $delay) use (&$delays): array {
                self::assertSame(['new'], $skus);
                $delays[] = $delay;
                return [];
            }
        );
        $executor = $this->createMock(MutationExecutorInterface::class);
        $executor->expects(self::never())->method('execute');
        $original = $this->mutationResult('new');

        $result = $this->recovery($visibility, $executor)->recover([$original], ['new'])[0];

        self::assertSame([0, 100, 200, 300, 500, 800], $delays);
        self::assertSame(MutationResultInterface::STATUS_UNRESOLVED, $result->getStatus());
        self::assertSame($original->getErrors()[0], $result->getErrors()[0]);
        self::assertStringContainsString('5 retry iterations', $result->getErrors()[1]['message']);
        self::assertStringContainsString('mapping was retained', $result->getErrors()[1]['message']);
    }

    public function testCustomIterationLimitAndDelayScheduleAreUsed(): void
    {
        $visibility = $this->createMock(NewProductVisibility::class);
        $delays = [];
        $visibility->expects(self::exactly(3))->method('load')->willReturnCallback(
            static function (array $_skus, int $delay) use (&$delays): array {
                $delays[] = $delay;
                return [];
            }
        );
        $executor = $this->createMock(MutationExecutorInterface::class);
        $executor->expects(self::never())->method('execute');

        $this->recovery($visibility, $executor, 2, [30.0, '40'])->recover([$this->mutationResult('new')], ['new']);

        self::assertSame([0, 30, 40], $delays);
    }

    public function testReadFailureIsReportedWithoutLosingOriginalMutationError(): void
    {
        $visibility = $this->createMock(NewProductVisibility::class);
        $visibility->expects(self::once())->method('load')
            ->willThrowException(new LocalizedException(__('Read permission denied.')));
        $executor = $this->createMock(MutationExecutorInterface::class);
        $executor->expects(self::never())->method('execute');
        $original = $this->mutationResult('new');

        $result = $this->recovery($visibility, $executor)->recover([$original], ['new'])[0];

        self::assertSame($original->getErrors()[0], $result->getErrors()[0]);
        self::assertStringContainsString('Read permission denied.', $result->getErrors()[1]['message']);
        self::assertSame(MutationResultInterface::STATUS_UNRESOLVED, $result->getStatus());
    }

    private function mutationResult(
        string $sku,
        string $status = MutationResultInterface::STATUS_VALIDATION_FAILURE
    ): MutationResultInterface {
        return new MutationResult(
            $status,
            'original_' . $sku,
            (new ProductMutationFactory())->setStatus($sku, 'en_GB', 'enabled'),
            errors: $status === MutationResultInterface::STATUS_SUCCESS ? [] : [
                ['message' => 'An unknown error occurred.', 'extensions' => ['code' => 'VALIDATION_ERROR']],
            ]
        );
    }

    /** @param array<int, int|float|string> $delays */
    private function recovery(
        NewProductVisibility $visibility,
        MutationExecutorInterface $executor,
        int $maxIterations = 5,
        array $delays = [100, 200, 300, 500, 800]
    ): NewProductMutationRecovery {
        $planner = $this->createStub(MutationBatchPlannerInterface::class);
        $planner->method('plan')->willReturnCallback(static function (array $operations): array {
            $aliases = [];
            foreach ($operations as $index => $operation) {
                $aliases['retry_' . $index] = $operation;
            }
            return [new MutationBatch('mutation { test }', [], $aliases)];
        });
        return new NewProductMutationRecovery(
            $visibility,
            $planner,
            $executor,
            new ProductMutationFailureClassifier(),
            $maxIterations,
            $delays
        );
    }
}
