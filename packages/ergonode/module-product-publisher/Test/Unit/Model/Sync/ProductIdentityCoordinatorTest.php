<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\Unit\Model\Sync;

use Ergonode\Product\Api\ProductIdentityModeProviderInterface;
use Ergonode\Product\Model\Config\ProductIdentityModeProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Ergonode\ProductPublisher\Api\Data\ProductCreationContextInterface;
use Ergonode\ProductPublisher\Api\Data\ProductSynchronizationResultInterface;
use Ergonode\ProductPublisher\Api\ProductIdentityRegistryInterface;
use Ergonode\ProductPublisher\Api\ProductPublicationProductResolverInterface;
use Ergonode\ProductPublisher\Model\Data\ProductState;
use Ergonode\ProductPublisher\Model\GraphQl\ProductMutationFactory;
use Ergonode\ProductPublisher\Model\Sync\ProductIdentityCoordinator;
use Ergonode\ProductPublisher\Model\Sync\ProductMutationFailureClassifier;
use Ergonode\Publisher\Api\Data\MutationBatchInterface;
use Ergonode\Publisher\Api\Data\MutationResultInterface;
use Ergonode\Publisher\Api\MutationBatchPlannerInterface;
use Ergonode\Publisher\Api\MutationExecutorInterface;
use Ergonode\Publisher\Model\Data\MutationResult;
use Ergonode\Publisher\Model\Data\SynchronizationResult;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Ergonode\ProductPublisher\Model\Sync\ProductMutationFailureReporter;
use Psr\Log\NullLogger;

class ProductIdentityCoordinatorTest extends TestCase
{
    public function testAmbiguousCreateIsAttentionAndIsNotBoundOrRetried(): void
    {
        $state = new ProductState(
            'MAGENTO-1',
            'simple',
            'template',
            magentoProductId: 42,
            identityMode: ProductIdentityInterface::MODE_ASSIGNED
        );
        $operation = (new ProductMutationFactory())->createWithAssignedSku($state);
        $registry = $this->createMock(ProductIdentityRegistryInterface::class);
        $registry->expects(self::never())->method('bindAssignedBatch');
        $executor = $this->createMock(MutationExecutorInterface::class);
        $executor->expects(self::once())->method('execute')->willReturn(new SynchronizationResult([
            new MutationResult(MutationResultInterface::STATUS_UNRESOLVED, 'create', $operation),
        ]));

        $result = $this->coordinator($registry, $executor)->prepare(['MAGENTO-1' => $state]);

        self::assertSame([], $result->getStates());
        self::assertSame(
            ProductSynchronizationResultInterface::STATUS_ATTENTION,
            $result->getFailures()['MAGENTO-1']['status']
        );
        self::assertStringContainsString('may have succeeded', $result->getFailures()['MAGENTO-1']['message']);
        self::assertStringContainsString(
            'Creation does not write the Magento SKU attribute',
            $result->getFailures()['MAGENTO-1']['message']
        );
    }

    public function testGeneratedSkuIsBoundBeforeTheStateContinues(): void
    {
        $state = new ProductState(
            'MAGENTO-1',
            'simple',
            'template',
            magentoProductId: 42,
            identityMode: ProductIdentityInterface::MODE_ASSIGNED
        );
        $operation = (new ProductMutationFactory())->createWithAssignedSku($state);
        $registry = $this->createMock(ProductIdentityRegistryInterface::class);
        $registry->expects(self::once())->method('bindAssignedBatch')->with([42 => 'ERG-123']);
        $executor = $this->createStub(MutationExecutorInterface::class);
        $executor->method('execute')->willReturn(new SynchronizationResult([
            new MutationResult(
                MutationResultInterface::STATUS_SUCCESS,
                'create',
                $operation,
                ['product' => ['sku' => 'ERG-123']]
            ),
        ]));

        $result = $this->coordinator($registry, $executor)->prepare(['MAGENTO-1' => $state]);

        self::assertSame([], $result->getFailures());
        $preparedState = $result->getStates()['MAGENTO-1'];
        self::assertSame('ERG-123', $preparedState->getSku());
        self::assertInstanceOf(ProductCreationContextInterface::class, $preparedState);
        self::assertTrue($preparedState->wasCreatedInCurrentSynchronization());
    }

    public function testCreatedAssignedProductWithFailedLocalBindingNeedsAttention(): void
    {
        $state = new ProductState(
            'MAGENTO-1',
            'simple',
            'template',
            magentoProductId: 42,
            identityMode: ProductIdentityInterface::MODE_ASSIGNED
        );
        $operation = (new ProductMutationFactory())->createWithAssignedSku($state);
        $registry = $this->createMock(ProductIdentityRegistryInterface::class);
        $registry->expects(self::once())->method('bindAssignedBatch')
            ->with([42 => 'ERG-123'])
            ->willThrowException(new LocalizedException(__('Database unavailable.')));
        $executor = $this->createStub(MutationExecutorInterface::class);
        $executor->method('execute')->willReturn(new SynchronizationResult([
            new MutationResult(
                MutationResultInterface::STATUS_SUCCESS,
                'create',
                $operation,
                ['product' => ['sku' => 'ERG-123']]
            ),
        ]));

        $result = $this->coordinator($registry, $executor)->prepare(['MAGENTO-1' => $state]);

        self::assertSame([], $result->getStates());
        self::assertSame(
            ProductSynchronizationResultInterface::STATUS_ATTENTION,
            $result->getFailures()['MAGENTO-1']['status']
        );
        self::assertStringContainsString('ERG-123', $result->getFailures()['MAGENTO-1']['message']);
    }

    public function testAssignedCreateConflictRequiresRemoteIdentityReconciliation(): void
    {
        $state = new ProductState(
            'MAGENTO-1',
            'simple',
            'template',
            magentoProductId: 42,
            identityMode: ProductIdentityInterface::MODE_ASSIGNED
        );
        $operation = (new ProductMutationFactory())->createWithAssignedSku($state);
        $registry = $this->createMock(ProductIdentityRegistryInterface::class);
        $registry->expects(self::never())->method('bindAssignedBatch');
        $executor = $this->createStub(MutationExecutorInterface::class);
        $executor->method('execute')->willReturn(new SynchronizationResult([
            new MutationResult(
                MutationResultInterface::STATUS_VALIDATION_FAILURE,
                'create',
                $operation,
                errors: [['extensions' => ['code' => 'ALREADY_EXISTS']]]
            ),
        ]));

        $result = $this->coordinator($registry, $executor)->prepare(['MAGENTO-1' => $state]);

        self::assertSame(
            ProductSynchronizationResultInterface::STATUS_ATTENTION,
            $result->getFailures()['MAGENTO-1']['status']
        );
        self::assertStringContainsString(
            'its native SKU is unknown',
            $result->getFailures()['MAGENTO-1']['message']
        );
        self::assertStringContainsString(
            'reconcile its native SKU binding before retrying',
            $result->getFailures()['MAGENTO-1']['message']
        );
        self::assertStringNotContainsString('next import', $result->getFailures()['MAGENTO-1']['message']);
    }

    public function testRejectedAssignedSkuCreateDoesNotBlockIndependentProduct(): void
    {
        $rejected = new ProductState(
            'MAGENTO-1',
            'simple',
            'template',
            magentoProductId: 42,
            identityMode: ProductIdentityInterface::MODE_ASSIGNED
        );
        $accepted = new ProductState(
            'MAGENTO-2',
            'simple',
            'template',
            magentoProductId: 43,
            identityMode: ProductIdentityInterface::MODE_ASSIGNED
        );
        $factory = new ProductMutationFactory();
        $registry = $this->createMock(ProductIdentityRegistryInterface::class);
        $registry->expects(self::once())->method('bindAssignedBatch')->with([43 => 'ERG-456']);
        $batch = $this->createStub(MutationBatchInterface::class);
        $planner = $this->createMock(MutationBatchPlannerInterface::class);
        $planner->expects(self::once())
            ->method('plan')
            ->with(self::callback(static fn (array $operations): bool => count($operations) === 2))
            ->willReturn([$batch]);
        $executor = $this->createMock(MutationExecutorInterface::class);
        $executor->expects(self::once())->method('execute')->willReturn(
            new SynchronizationResult([
                new MutationResult(
                    MutationResultInterface::STATUS_VALIDATION_FAILURE,
                    'create-one',
                    $factory->createWithAssignedSku($rejected),
                    errors: [['message' => 'Automatic SKU generation is disabled.']]
                ),
                new MutationResult(
                    MutationResultInterface::STATUS_SUCCESS,
                    'create-two',
                    $factory->createWithAssignedSku($accepted),
                    ['product' => ['sku' => 'ERG-456']]
                ),
            ])
        );

        $result = $this->coordinator($registry, $executor, planner: $planner)->prepare([
            'MAGENTO-1' => $rejected,
            'MAGENTO-2' => $accepted,
        ]);

        self::assertSame(
            ProductSynchronizationResultInterface::STATUS_FAILED,
            $result->getFailures()['MAGENTO-1']['status']
        );
        self::assertStringContainsString(
            'Automatic SKU generation is disabled.',
            $result->getFailures()['MAGENTO-1']['message']
        );
        self::assertStringContainsString('productCreateSimple', $result->getFailures()['MAGENTO-1']['message']);
        self::assertStringContainsString('Log reference:', $result->getFailures()['MAGENTO-1']['message']);
        self::assertSame('ERG-456', $result->getStates()['MAGENTO-2']->getSku());
    }

    public function testRemovingAttributeSupportBlocksAssignedBindingsButKeepsSharedPublication(): void
    {
        $shared = new ProductState('SHARED', 'simple', 'default', magentoProductId: 1, ergonodeSku: 'SHARED');
        $assigned = new ProductState(
            'MAGENTO-1',
            'simple',
            'default',
            magentoProductId: 2,
            ergonodeSku: 'ERG-1',
            identityMode: ProductIdentityInterface::MODE_ASSIGNED
        );
        $executor = $this->createMock(MutationExecutorInterface::class);
        $executor->expects(self::never())->method('execute');
        $registry = $this->createMock(ProductIdentityRegistryInterface::class);
        $registry->expects(self::never())->method('bindAssignedBatch');
        $coordinator = $this->coordinator(
            $registry,
            $executor,
            identityModeProvider: new ProductIdentityModeProvider($this->createStub(ScopeConfigInterface::class))
        );

        $result = $coordinator->prepare(['SHARED' => $shared, 'MAGENTO-1' => $assigned]);

        self::assertSame(['SHARED'], array_keys($result->getStates()));
        self::assertSame('SHARED', $result->getStates()['SHARED']->getSku());
        self::assertSame(
            ProductSynchronizationResultInterface::STATUS_BLOCKED_REFERENCE,
            $result->getFailures()['MAGENTO-1']['status']
        );
        self::assertStringContainsString(
            'existing identities are preserved',
            $result->getFailures()['MAGENTO-1']['message']
        );
    }

    private function coordinator(
        ProductIdentityRegistryInterface $registry,
        MutationExecutorInterface $executor,
        ?MutationBatchPlannerInterface $planner = null,
        ?ProductIdentityModeProviderInterface $identityModeProvider = null
    ): ProductIdentityCoordinator {
        $identityModeProvider ??= $this->createStub(ProductIdentityModeProviderInterface::class);
        if ($planner === null) {
            $batch = $this->createStub(MutationBatchInterface::class);
            $planner = $this->createStub(MutationBatchPlannerInterface::class);
            $planner->method('plan')->willReturn([$batch]);
        }
        return new ProductIdentityCoordinator(
            $registry,
            $identityModeProvider,
            new ProductMutationFactory(),
            $planner,
            $executor,
            $this->createStub(ProductPublicationProductResolverInterface::class),
            new ProductMutationFailureClassifier(),
            new ProductMutationFailureReporter(new NullLogger())
        );
    }
}
