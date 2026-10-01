<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisher\Test\Unit\Model\Sync;

use Ergonode\CategoryAttributePublisher\Api\WriteScopeCategoryAttributeCodeLoaderInterface;
use Ergonode\CategoryAttributePublisher\Model\GraphQl\CategoryAttributeMutationFactory;
use Ergonode\CategoryAttributePublisher\Model\Sync\CategoryAttributeRegistrySynchronizer;
use Ergonode\Publisher\Api\AmbiguousMutationVerifierInterface;
use Ergonode\Publisher\Api\Data\MutationBatchInterface;
use Ergonode\Publisher\Api\Data\SynchronizationResultInterface;
use Ergonode\Publisher\Api\MutationBatchPlannerInterface;
use Ergonode\Publisher\Api\MutationExecutorInterface;
use Ergonode\Publisher\Model\GraphQl\SynchronizationRateLimitGuard;
use Ergonode\Publisher\Model\Data\MutationResult;
use Ergonode\Publisher\Model\Data\SynchronizationResult;
use Ergonode\Core\Api\Exception\GraphQlRequestException;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class CategoryAttributeRegistrySynchronizerTest extends TestCase
{
    public function testExistingAttributeIsNoop(): void
    {
        $loader = $this->createStub(WriteScopeCategoryAttributeCodeLoaderInterface::class);
        $loader->method('loadWriteScope')->willReturn(['description']);
        $planner = $this->createMock(MutationBatchPlannerInterface::class);
        $planner->expects(self::never())->method('plan');

        (new CategoryAttributeRegistrySynchronizer(
            $loader,
            new CategoryAttributeMutationFactory(),
            $planner,
            $this->createStub(MutationExecutorInterface::class),
            new SynchronizationRateLimitGuard()
        ))->ensure('description');
    }

    public function testRegistersMissingAttributeAndVerifiesResult(): void
    {
        $loader = $this->createMock(WriteScopeCategoryAttributeCodeLoaderInterface::class);
        $loader->expects(self::exactly(2))
            ->method('loadWriteScope')
            ->willReturnOnConsecutiveCalls([], ['description']);
        $batch = $this->createStub(MutationBatchInterface::class);
        $planner = $this->createMock(MutationBatchPlannerInterface::class);
        $planner->expects(self::once())->method('plan')->with(self::callback(static function (array $operations): bool {
            return count($operations) === 1
                && $operations[0]->getField() === 'categoryAttributeAddAttribute';
        }))->willReturn([$batch]);
        $result = $this->createStub(SynchronizationResultInterface::class);
        $result->method('isSuccessful')->willReturn(true);
        $executor = $this->createMock(MutationExecutorInterface::class);
        $executor->expects(self::once())->method('execute')->with(
            $batch,
            self::isInstanceOf(AmbiguousMutationVerifierInterface::class)
        )->willReturn($result);

        (new CategoryAttributeRegistrySynchronizer(
            $loader,
            new CategoryAttributeMutationFactory(),
            $planner,
            $executor,
            new SynchronizationRateLimitGuard()
        ))->ensure('description');
    }

    public function testPreservesRateLimitAfterExecutorRetriesAreExhausted(): void
    {
        $loader = $this->createStub(WriteScopeCategoryAttributeCodeLoaderInterface::class);
        $loader->method('loadWriteScope')->willReturn([]);
        $factory = new CategoryAttributeMutationFactory();
        $operation = $factory->addAllowedAttribute('description');
        $result = new SynchronizationResult([
            new MutationResult(
                'transient_failure',
                'm0',
                $operation,
                null,
                [['message' => 'Rate limited', 'extensions' => [
                    'failure_type' => 'rate_limit', 'retry_after_seconds' => 30,
                ]]]
            ),
        ]);
        $planner = $this->createStub(MutationBatchPlannerInterface::class);
        $planner->method('plan')->willReturn([$this->createStub(MutationBatchInterface::class)]);
        $executor = $this->createStub(MutationExecutorInterface::class);
        $executor->method('execute')->willReturn($result);
        try {
            (new CategoryAttributeRegistrySynchronizer(
                $loader,
                $factory,
                $planner,
                $executor,
                new SynchronizationRateLimitGuard()
            ))->ensure('description');
            self::fail('Expected rate limit.');
        } catch (GraphQlRequestException $exception) {
            self::assertSame(30, $exception->getRetryAfterSeconds());
            self::assertSame('rate_limit', $exception->getFailureType());
        }
    }

    public function testValidationFailureRemainsAnOrdinaryFailure(): void
    {
        $loader = $this->createStub(WriteScopeCategoryAttributeCodeLoaderInterface::class);
        $loader->method('loadWriteScope')->willReturn([]);
        $factory = new CategoryAttributeMutationFactory();
        $result = new SynchronizationResult([
            new MutationResult('validation_failure', 'm0', $factory->addAllowedAttribute('description')),
        ]);
        $planner = $this->createStub(MutationBatchPlannerInterface::class);
        $planner->method('plan')->willReturn([$this->createStub(MutationBatchInterface::class)]);
        $executor = $this->createStub(MutationExecutorInterface::class);
        $executor->method('execute')->willReturn($result);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unable to register');
        (new CategoryAttributeRegistrySynchronizer(
            $loader,
            $factory,
            $planner,
            $executor,
            new SynchronizationRateLimitGuard()
        ))->ensure('description');
    }
}
