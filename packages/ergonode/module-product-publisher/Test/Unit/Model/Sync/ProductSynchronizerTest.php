<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\Unit\Model\Sync;

use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Ergonode\Core\Api\GraphQlWriteScopeQueryClientInterface;
use Ergonode\ProductPublisher\Api\Data\ProductCreationContextInterface;
use Ergonode\ProductPublisher\Api\Data\ProductSynchronizationResultInterface;
use Ergonode\ProductPublisher\Api\ProductIdentityRegistryInterface;
use Ergonode\ProductPublisher\Model\Data\ProductRelationState;
use Ergonode\ProductPublisher\Model\Data\ProductAttributeValue;
use Ergonode\ProductPublisher\Model\Data\ProductState;
use Ergonode\ProductPublisher\Model\GraphQl\ProductMutationFactory;
use Ergonode\ProductPublisher\Model\Sync\ProductMutationFailureClassifier;
use Ergonode\ProductPublisher\Model\Sync\ProductPublicationMutationPlanner;
use Ergonode\ProductPublisher\Model\Sync\ProductSynchronizer;
use Ergonode\Publisher\Api\Data\MutationOperationInterface;
use Ergonode\Publisher\Api\Data\MutationResultInterface;
use Ergonode\Publisher\Api\MutationBatchPlannerInterface;
use Ergonode\Publisher\Api\MutationExecutorInterface;
use Ergonode\Publisher\Model\Data\MutationBatch;
use Ergonode\Publisher\Model\Data\MutationResult;
use Ergonode\Publisher\Model\Data\SynchronizationResult;
use Ergonode\Publisher\Model\GraphQl\MutationBatchPlanner;
use Ergonode\Publisher\Model\GraphQl\MutationBatchBuilder;
use Ergonode\Publisher\Model\GraphQl\MutationAliasGenerator;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use Ergonode\ProductPublisher\Model\GraphQl\RemoteProductPublicationStateLoader;
use Ergonode\ProductPublisher\Model\Sync\ProductMutationFailureReporter;
use Psr\Log\NullLogger;
use Ergonode\ProductPublisher\Model\Sync\NewProductMutationRecovery;
use Ergonode\ProductPublisher\Model\Sync\NewProductVisibility;
use Magento\Framework\Exception\LocalizedException;

class ProductSynchronizerTest extends TestCase
{
    public function testHundredNewProductsSkip11856ClearsAfterCreationAndBinding(): void
    {
        $states = [];
        for ($index = 0; $index < 100; ++$index) {
            $values = [];
            $clearCount = $index < 50 ? ($index < 22 ? 119 : 118) : ($index < 84 ? 119 : 118);
            $writeCount = $index < 50 ? ($index < 44 ? 4 : 3) : ($index < 82 ? 4 : 3);
            for ($attribute = 0; $attribute < $clearCount; ++$attribute) {
                $values[] = new ProductAttributeValue(
                    'empty_' . $attribute,
                    'text',
                    [],
                    clearedLanguageCodes: ['pl_PL']
                );
            }
            for ($attribute = 0; $attribute < $writeCount; ++$attribute) {
                $values[] = new ProductAttributeValue('filled_' . $attribute, 'text', ['pl_PL' => 'value']);
            }
            $states[] = new ProductState(
                'NAV-' . $index,
                'simple',
                'default',
                values: $values,
                magentoProductId: $index + 1,
                identityMode: ProductIdentityInterface::MODE_MAPPED,
                ergonodeSku: 'NAV-' . $index
            );
        }
        $bound = [];
        $registry = $this->createStub(ProductIdentityRegistryInterface::class);
        $registry->method('bindMappedBatch')->willReturnCallback(static function (array $bindings) use (&$bound): void {
            $bound += $bindings;
        });
        $visibility = $this->createStub(NewProductVisibility::class);
        $visibility->method('load')->willReturnCallback(static function (array $skus) use (&$bound): array {
            return array_values(array_intersect($skus, $bound));
        });
        $remote = $this->createStub(RemoteProductPublicationStateLoader::class);
        $remote->method('load')->willReturnCallback(static function (array $skus) use (&$bound): array {
            $snapshots = [];
            foreach ($skus as $sku) {
                self::assertContains($sku, $bound, 'Comparison must follow confirmed CREATE and durable binding.');
                $snapshots['sku:' . $sku] = ['template' => 'default', 'translations' => []];
            }
            return $snapshots;
        });
        $sizes = [];
        $executor = $this->createStub(MutationExecutorInterface::class);
        $executor->method('execute')->willReturnCallback(function (MutationBatch $batch) use (&$sizes) {
            $sizes[] = count($batch->getOperationsByAlias());
            return $this->successfulBatch($batch);
        });
        $batchPlanner = new MutationBatchPlanner(
            new MutationBatchBuilder(
                new MutationAliasGenerator(),
                new Json()
            )
        );
        $synchronizer = $this->synchronizer([], $executor, $batchPlanner, $visibility, $registry, $remote);
        $baseline = new ProductPublicationMutationPlanner(new ProductMutationFactory(), $remote);
        $oldMutations = 0;
        $oldBatches = 0;
        foreach (array_chunk($states, 50) as $chunk) {
            $indexed = [];
            foreach ($chunk as $state) {
                $indexed[$state->getSku()] = $state;
            }
            $plan = [...array_map($baseline->create(...), $chunk), ...$baseline->operations($indexed, [])];
            $oldMutations += count($plan);
            $oldBatches += count($batchPlanner->plan($plan));
            foreach ($synchronizer->synchronizeBatch($chunk) as $result) {
                self::assertSame(ProductSynchronizationResultInterface::STATUS_SUCCESS, $result->getStatus());
                self::assertNotContains(
                    'productDeleteAttributeValueTranslations',
                    $this->fields($result->getResults())
                );
            }
        }
        self::assertSame(12332, $oldMutations);
        self::assertSame(248, $oldBatches);
        self::assertSame(476, array_sum($sizes));
        self::assertCount(10, $sizes);
        self::assertCount(100, $bound);
        $sizes = [];
        foreach (array_chunk($states, 50) as $chunk) {
            foreach ($synchronizer->synchronizeBatch($chunk) as $result) {
                self::assertNotContains('productCreateSimple', $this->fields($result->getResults()));
            }
        }
        self::assertSame(376, array_sum($sizes));
        self::assertCount(8, $sizes);
    }

    public function testAssignedCreationDeletesPresentDefaultsOnlyInRequestedLanguages(): void
    {
        $state = $this->newProductState([
            new ProductAttributeValue('title', 'text', [], clearedLanguageCodes: ['pl_PL', 'en_GB']),
            new ProductAttributeValue('absent', 'text', [], clearedLanguageCodes: ['pl_PL']),
        ]);
        $remote = $this->createStub(RemoteProductPublicationStateLoader::class);
        $remote->method('load')->willReturn([
            'sku:ERG-NEW' => ['template' => 'default', 'translations' => ['title' => ['pl_PL' => true]]],
        ]);
        $executor = $this->createStub(MutationExecutorInterface::class);
        $executor->method('execute')->willReturnCallback($this->successfulBatch(...));
        $result = $this->synchronizer(
            [42 => $this->identity(42, 'ERG-NEW', ProductIdentityInterface::MODE_ASSIGNED)],
            $executor,
            remoteState: $remote
        )->synchronize($state);
        self::assertSame([
            'productSetStatus', 'productSetStatus', 'productDeleteAttributeValueTranslations',
        ], $this->fields($result->getResults()));
        $input = $result->getResults()[2]->getOperation()->getVariables()['input']->getValue();
        self::assertSame(['pl_PL'], $input['languages']);
    }

    public function testNewProductPreservesClearsWhenComparisonIsUnavailableOrTemplateDiffers(): void
    {
        foreach ([[], ['sku:NEW' => ['template' => 'other', 'translations' => []]]] as $snapshot) {
            $remote = $this->createStub(RemoteProductPublicationStateLoader::class);
            $remote->method('load')->willReturn($snapshot);
            $executor = $this->createStub(MutationExecutorInterface::class);
            $executor->method('execute')->willReturnCallback($this->successfulBatch(...));
            $result = $this->synchronizer([], $executor, remoteState: $remote)->synchronize(new ProductState(
                'NEW',
                'simple',
                'default',
                values: [new ProductAttributeValue('title', 'text', [], clearedLanguageCodes: ['pl_PL'])]
            ));
            self::assertSame([
                'productCreateSimple', 'productDeleteAttributeValueTranslations',
            ], $this->fields($result->getResults()));
        }
    }

    public function testMappedCreationContextSurvivesStagesForVisibilityRecovery(): void
    {
        $visibility = $this->createMock(NewProductVisibility::class);
        $visibility->expects(self::exactly(3))->method('load')->willReturnOnConsecutiveCalls([], [], ['NAV-42']);
        $writes = 0;
        $creates = 0;
        $executor = $this->createStub(MutationExecutorInterface::class);
        $executor->method('execute')->willReturnCallback(function (MutationBatch $batch) use (&$writes, &$creates) {
            foreach ($batch->getOperationsByAlias() as $alias => $operation) {
                if ($operation->getField() === 'productCreateSimple') {
                    ++$creates;
                } elseif (++$writes === 1) {
                    return new SynchronizationResult([new MutationResult(
                        MutationResultInterface::STATUS_VALIDATION_FAILURE,
                        $alias,
                        $operation,
                        errors: [['message' => 'An unknown error occurred.']]
                    )]);
                }
            }
            return $this->successfulBatch($batch);
        });
        $result = $this->synchronizer([], $executor, visibility: $visibility)
            ->synchronize($this->mappedState(statuses: ['pl_PL' => 'active']));
        self::assertSame(ProductSynchronizationResultInterface::STATUS_SUCCESS, $result->getStatus());
        self::assertSame(1, $creates);
        self::assertSame(2, $writes);
    }

    public function testOversizedValueStillBlocksCreateBeforeAnyRemoteWrite(): void
    {
        $executor = $this->createMock(MutationExecutorInterface::class);
        $executor->expects(self::never())->method('execute');
        $batchPlanner = new MutationBatchPlanner(
            new MutationBatchBuilder(
                new MutationAliasGenerator(),
                new Json()
            )
        );
        $result = $this->synchronizer([], $executor, $batchPlanner)->synchronize(new ProductState(
            'NEW',
            'simple',
            'default',
            values: [new ProductAttributeValue('title', 'text', ['pl_PL' => str_repeat('x', 524288)])]
        ));
        self::assertSame(ProductSynchronizationResultInterface::STATUS_FAILED, $result->getStatus());
        self::assertNotContains('productCreateSimple', $this->fields($result->getResults()));
    }

    public function testCreatedDefaultDeleteFailureRetainsBindingsAndIndependentProductSuccess(): void
    {
        $states = [];
        foreach (['FIRST', 'SECOND'] as $index => $sku) {
            $states[] = new ProductState(
                $sku,
                'simple',
                'default',
                values: [new ProductAttributeValue('title', 'text', [], clearedLanguageCodes: ['pl_PL'])],
                magentoProductId: $index + 1,
                identityMode: ProductIdentityInterface::MODE_MAPPED,
                ergonodeSku: $sku
            );
        }
        $bound = [];
        $registry = $this->createStub(ProductIdentityRegistryInterface::class);
        $registry->method('bindMappedBatch')->willReturnCallback(static function (array $bindings) use (&$bound): void {
            $bound += $bindings;
        });
        $remote = $this->createStub(RemoteProductPublicationStateLoader::class);
        $remote->method('load')->willReturnCallback(static function (array $skus) use (&$bound): array {
            $snapshots = [];
            foreach ($skus as $sku) {
                self::assertContains($sku, $bound);
                $snapshots['sku:' . $sku] = [
                    'template' => 'default', 'translations' => ['title' => ['pl_PL' => true]],
                ];
            }
            return $snapshots;
        });
        $executor = $this->createStub(MutationExecutorInterface::class);
        $executor->method('execute')->willReturnCallback(static function (MutationBatch $batch) {
            $results = [];
            foreach ($batch->getOperationsByAlias() as $alias => $operation) {
                $failed = $operation->getField() === 'productDeleteAttributeValueTranslations'
                    && $operation->getMetadata()['entity_sku'] === 'FIRST';
                $results[] = new MutationResult(
                    $failed
                        ? MutationResultInterface::STATUS_PERMANENT_FAILURE
                        : MutationResultInterface::STATUS_SUCCESS,
                    $alias,
                    $operation
                );
            }
            return new SynchronizationResult($results);
        });
        $results = $this->synchronizer([], $executor, registry: $registry, remoteState: $remote)
            ->synchronizeBatch($states);
        self::assertSame(ProductSynchronizationResultInterface::STATUS_FAILED, $results[0]->getStatus());
        self::assertSame(ProductSynchronizationResultInterface::STATUS_SUCCESS, $results[1]->getStatus());
        self::assertSame([1 => 'FIRST', 2 => 'SECOND'], $bound);
    }

    public function testMatchingRemoteStateWithoutLocalBindingCanSucceedWithoutMutations(): void
    {
        $visibility = $this->createStub(NewProductVisibility::class);
        $visibility->method('load')->willReturn(['NAV-42']);
        $remote = $this->createStub(RemoteProductPublicationStateLoader::class);
        $remote->method('load')->willReturn([
            'sku:NAV-42' => ['template' => 'default', 'translations' => []],
        ]);
        $executor = $this->createMock(MutationExecutorInterface::class);
        $executor->expects(self::never())->method('execute');

        $result = $this->synchronizer([], $executor, visibility: $visibility, remoteState: $remote)
            ->synchronize($this->mappedState());

        self::assertSame(ProductSynchronizationResultInterface::STATUS_SUCCESS, $result->getStatus());
        self::assertSame([], $result->getResults());
    }

    public function testComparisonPreservesRealDeleteFailuresAndOtherProductSuccess(): void
    {
        $states = [];
        $snapshots = [];
        foreach (['FIRST', 'SECOND'] as $index => $sku) {
            $states[] = new ProductState($sku, 'simple', 'default', values: [
                new ProductAttributeValue('title', 'text', [], clearedLanguageCodes: ['pl_PL', 'en_GB']),
            ], magentoProductId: 42 + $index);
            $snapshots['sku:' . $sku] = [
                'template' => 'default', 'translations' => ['title' => ['pl_PL' => true]],
            ];
        }
        $remote = $this->createStub(RemoteProductPublicationStateLoader::class);
        $remote->method('load')->willReturn($snapshots);
        $executor = $this->createMock(MutationExecutorInterface::class);
        $executor->expects(self::once())->method('execute')->willReturnCallback(function (MutationBatch $batch) {
            $results = [];
            foreach ($batch->getOperationsByAlias() as $alias => $operation) {
                self::assertSame('productDeleteAttributeValueTranslations', $operation->getField());
                self::assertSame(['pl_PL'], $operation->getVariables()['input']->getValue()['languages']);
                $failed = $operation->getMetadata()['entity_sku'] === 'FIRST';
                $results[] = new MutationResult(
                    $failed
                        ? MutationResultInterface::STATUS_PERMANENT_FAILURE
                        : MutationResultInterface::STATUS_SUCCESS,
                    $alias,
                    $operation,
                    errors: $failed ? [['message' => 'Delete rejected.']] : []
                );
            }
            return new SynchronizationResult($results);
        });
        $results = $this->synchronizer(
            [42 => $this->identity(42, 'FIRST'), 43 => $this->identity(43, 'SECOND')],
            $executor,
            remoteState: $remote
        )->synchronizeBatch($states);

        self::assertSame(ProductSynchronizationResultInterface::STATUS_FAILED, $results[0]->getStatus());
        self::assertSame(ProductSynchronizationResultInterface::STATUS_SUCCESS, $results[1]->getStatus());
        self::assertCount(1, $results[0]->getResults());
        self::assertCount(1, $results[1]->getResults());
    }

    public function testMappedSkuAlreadyRemoteWithoutLocalBindingIsUpdated(): void
    {
        $state = $this->mappedState();
        $visibility = $this->createMock(NewProductVisibility::class);
        $visibility->expects(self::once())->method('load')->with(['NAV-42'])->willReturn(['NAV-42']);
        $executor = $this->createStub(MutationExecutorInterface::class);
        $executor->method('execute')->willReturnCallback($this->successfulBatch(...));

        $result = $this->synchronizer([], $executor, visibility: $visibility)->synchronize($state);

        self::assertSame(ProductSynchronizationResultInterface::STATUS_SUCCESS, $result->getStatus());
        self::assertSame(['productSetTemplate'], $this->fields($result->getResults()));
    }

    public function testAbsentMappedSkuIsCreatedAndBoundBeforeLaterMutationFails(): void
    {
        $state = $this->mappedState(statuses: ['pl_PL' => 'active']);
        $visibility = $this->createStub(NewProductVisibility::class);
        $visibility->method('load')->willReturn([]);
        $bound = false;
        $registry = $this->createMock(ProductIdentityRegistryInterface::class);
        $registry->expects(self::once())->method('bindMappedBatch')->with([42 => 'NAV-42'])
            ->willReturnCallback(static function () use (&$bound): void {
                $bound = true;
            });
        $executor = $this->createStub(MutationExecutorInterface::class);
        $executor->method('execute')->willReturnCallback(function (MutationBatch $batch): SynchronizationResult {
            $results = [];
            foreach ($batch->getOperationsByAlias() as $alias => $operation) {
                $create = $operation->getMetadata()['operation_key'] === 'create';
                $results[] = new MutationResult(
                    $create ? MutationResultInterface::STATUS_SUCCESS
                        : MutationResultInterface::STATUS_PERMANENT_FAILURE,
                    $alias,
                    $operation,
                    errors: $create ? [] : [['message' => 'Attribute rejected.']]
                );
            }
            return new SynchronizationResult($results);
        });

        $result = $this->synchronizer([], $executor, visibility: $visibility, registry: $registry)
            ->synchronize($state);

        self::assertTrue($bound);
        self::assertSame(ProductSynchronizationResultInterface::STATUS_FAILED, $result->getStatus());
        self::assertSame(['productCreateSimple', 'productSetStatus'], $this->fields($result->getResults()));
    }

    public function testMappedRemoteReadFailureDoesNotCreate(): void
    {
        $visibility = $this->createStub(NewProductVisibility::class);
        $visibility->method('load')->willThrowException(new LocalizedException(__('Remote read failed.')));
        $executor = $this->createMock(MutationExecutorInterface::class);
        $executor->expects(self::never())->method('execute');

        $result = $this->synchronizer(
            [42 => $this->identity(42, 'NAV-42', ProductIdentityInterface::MODE_MAPPED)],
            $executor,
            visibility: $visibility
        )->synchronize($this->mappedState());

        self::assertSame(ProductSynchronizationResultInterface::STATUS_FAILED, $result->getStatus());
        self::assertStringContainsString('Remote read failed.', $result->getMessage());
    }

    public function testIncompleteMappedRemoteResponseDoesNotCreate(): void
    {
        $client = $this->createStub(GraphQlWriteScopeQueryClientInterface::class);
        $client->method('queryWriteScope')->willReturn([]);
        $executor = $this->createMock(MutationExecutorInterface::class);
        $executor->expects(self::never())->method('execute');

        $result = $this->synchronizer(
            [42 => $this->identity(42, 'NAV-42', ProductIdentityInterface::MODE_MAPPED)],
            $executor,
            visibility: new NewProductVisibility($client)
        )->synchronize($this->mappedState());

        self::assertSame(ProductSynchronizationResultInterface::STATUS_FAILED, $result->getStatus());
        self::assertStringContainsString('omitted the visibility result', $result->getMessage());
    }

    public function testAmbiguousMappedCreateDoesNotConfirmIdentity(): void
    {
        $visibility = $this->createStub(NewProductVisibility::class);
        $visibility->method('load')->willReturn([]);
        $registry = $this->createMock(ProductIdentityRegistryInterface::class);
        $registry->expects(self::never())->method('bindMappedBatch');
        $executor = $this->createStub(MutationExecutorInterface::class);
        $executor->method('execute')->willReturnCallback(static function (MutationBatch $batch): SynchronizationResult {
            $alias = array_key_first($batch->getOperationsByAlias());
            return new SynchronizationResult([new MutationResult(
                MutationResultInterface::STATUS_TRANSIENT_FAILURE,
                $alias,
                $batch->getOperationsByAlias()[$alias],
                errors: [['message' => 'Transport interrupted.']]
            )]);
        });

        $result = $this->synchronizer([], $executor, visibility: $visibility, registry: $registry)
            ->synchronize($this->mappedState());

        self::assertSame(ProductSynchronizationResultInterface::STATUS_ATTENTION, $result->getStatus());
    }

    public function testConfirmedMappedBindingAllowsRecreationAfterExplicitRemoteAbsence(): void
    {
        $state = new ProductState(
            '740',
            'simple',
            'default',
            values: [new ProductAttributeValue('name', 'text', ['pl_PL' => 'Pierścień do jajek sadzonych'])],
            magentoProductId: 3,
            ergonodeSku: '740',
            identityMode: ProductIdentityInterface::MODE_MAPPED
        );
        $client = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $client->expects(self::exactly(2))->method('queryWriteScope')
            ->with(self::anything(), ['sku_0' => '740'])
            ->willReturnOnConsecutiveCalls(['product_0' => null], ['product_0' => ['sku' => '740']]);
        $registry = $this->createMock(ProductIdentityRegistryInterface::class);
        $registry->method('getIdentitiesByProductIds')->willReturn([
            3 => $this->identity(3, '740', ProductIdentityInterface::MODE_MAPPED),
        ]);
        $registry->expects(self::once())->method('bindMappedBatch')->with([3 => '740']);
        $executor = $this->createMock(MutationExecutorInterface::class);
        $executor->expects(self::exactly(3))->method('execute')->willReturnCallback($this->successfulBatch(...));
        $synchronizer = $this->synchronizer(
            [],
            $executor,
            visibility: new NewProductVisibility($client),
            registry: $registry
        );

        $created = $synchronizer->synchronize($state);
        self::assertSame(ProductSynchronizationResultInterface::STATUS_SUCCESS, $created->getStatus());
        self::assertSame(
            ['productCreateSimple', 'productAddAttributeValueTranslationsText'],
            $this->fields($created->getResults())
        );
        foreach ($created->getResults() as $operationResult) {
            self::assertSame('740', $operationResult->getOperation()->getMetadata()['entity_sku']);
        }

        $updated = $synchronizer->synchronize($state);
        self::assertSame(ProductSynchronizationResultInterface::STATUS_SUCCESS, $updated->getStatus());
        self::assertSame(
            ['productSetTemplate', 'productAddAttributeValueTranslationsText'],
            $this->fields($updated->getResults())
        );
    }

    public function testChangedMappedSkuCannotReplaceHistoricalBinding(): void
    {
        $visibility = $this->createMock(NewProductVisibility::class);
        $visibility->expects(self::never())->method('load');
        $executor = $this->createMock(MutationExecutorInterface::class);
        $executor->expects(self::never())->method('execute');

        $result = $this->synchronizer(
            [42 => $this->identity(42, 'OTHER-SKU', ProductIdentityInterface::MODE_MAPPED)],
            $executor,
            visibility: $visibility
        )->synchronize($this->mappedState());

        self::assertSame(ProductSynchronizationResultInterface::STATUS_CONFLICT, $result->getStatus());
    }

    public function testRepeatedMappedPublicationWithConfirmedBindingUpdates(): void
    {
        $visibility = $this->createStub(NewProductVisibility::class);
        $visibility->method('load')->willReturn(['NAV-42']);
        $executor = $this->createStub(MutationExecutorInterface::class);
        $executor->method('execute')->willReturnCallback($this->successfulBatch(...));

        $result = $this->synchronizer(
            [42 => $this->identity(42, 'NAV-42', ProductIdentityInterface::MODE_MAPPED)],
            $executor,
            visibility: $visibility
        )->synchronize($this->mappedState());

        self::assertSame(ProductSynchronizationResultInterface::STATUS_SUCCESS, $result->getStatus());
        self::assertSame(['productSetTemplate'], $this->fields($result->getResults()));
    }

    public function testHistoricalAssignedBindingCannotBecomeMapped(): void
    {
        $visibility = $this->createMock(NewProductVisibility::class);
        $visibility->expects(self::never())->method('load');
        $executor = $this->createMock(MutationExecutorInterface::class);
        $executor->expects(self::never())->method('execute');

        $result = $this->synchronizer(
            [42 => $this->identity(42, 'OLD', ProductIdentityInterface::MODE_ASSIGNED)],
            $executor,
            visibility: $visibility
        )->synchronize($this->mappedState());

        self::assertSame(ProductSynchronizationResultInterface::STATUS_CONFLICT, $result->getStatus());
        self::assertStringContainsString('Reconcile or migrate', $result->getMessage());
    }

    public function testMappedSkuBoundToAnotherMagentoProductStopsBeforeUpdate(): void
    {
        $registry = $this->createStub(ProductIdentityRegistryInterface::class);
        $registry->method('getIdentitiesByErgonodeSkus')->willReturn([
            $this->identity(99, 'NAV-42', ProductIdentityInterface::MODE_MAPPED),
        ]);
        $visibility = $this->createMock(NewProductVisibility::class);
        $visibility->expects(self::never())->method('load');
        $executor = $this->createMock(MutationExecutorInterface::class);
        $executor->expects(self::never())->method('execute');

        $result = $this->synchronizer([], $executor, visibility: $visibility, registry: $registry)
            ->synchronize($this->mappedState());

        self::assertSame(ProductSynchronizationResultInterface::STATUS_CONFLICT, $result->getStatus());
        self::assertStringContainsString('Magento product ID 99', $result->getMessage());
    }

    private function mappedState(array $statuses = []): ProductState
    {
        return new ProductState(
            'NAV-42',
            'simple',
            'default',
            statuses: $statuses,
            magentoProductId: 42,
            ergonodeSku: 'NAV-42',
            identityMode: ProductIdentityInterface::MODE_MAPPED
        );
    }

    public function testUnmappedProductsAreCreatedInOneBatchWithoutRemoteReads(): void
    {
        $first = new ProductState('SKU-1', 'simple', 'default');
        $second = new ProductState('SKU-2', 'simple', 'default');
        $executor = $this->createMock(MutationExecutorInterface::class);
        $executor->expects(self::once())->method('execute')->willReturnCallback($this->successfulBatch(...));

        $results = $this->synchronizer([], $executor)->synchronizeBatch([$first, $second]);

        self::assertSame(
            [
                ProductSynchronizationResultInterface::STATUS_SUCCESS,
                ProductSynchronizationResultInterface::STATUS_SUCCESS,
            ],
            array_map(static fn ($result): string => $result->getStatus(), $results)
        );
        self::assertSame(['productCreateSimple'], $this->fields($results[0]->getResults()));
        self::assertSame(['productCreateSimple'], $this->fields($results[1]->getResults()));
    }

    public function testDeletedMappedProductUsesDeleteMutation(): void
    {
        $state = new ProductState(
            'SKU-1',
            'simple',
            '',
            deleted: true,
            magentoProductId: 42
        );
        $executor = $this->createStub(MutationExecutorInterface::class);
        $executor->method('execute')->willReturnCallback($this->successfulBatch(...));

        $result = $this->synchronizer([42 => $this->identity(42, 'SKU-1')], $executor)
            ->synchronize($state, ProductSynchronizer::MODE_RECONCILE);

        self::assertSame(ProductSynchronizationResultInterface::STATUS_SUCCESS, $result->getStatus());
        self::assertSame(['productDelete'], $this->fields($result->getResults()));
    }

    public function testMappedProductUsesUpdateMutationsFromMagentoState(): void
    {
        $state = new ProductState(
            'SKU-1',
            'simple',
            'default',
            statuses: ['pl_PL' => 'active'],
            magentoProductId: 42
        );
        $fields = [];
        $executor = $this->createStub(MutationExecutorInterface::class);
        $executor->method('execute')->willReturnCallback(
            function (MutationBatch $batch) use (&$fields): SynchronizationResult {
                $fields = [...$fields, ...array_map(
                    static fn (MutationOperationInterface $operation): string => $operation->getField(),
                    array_values($batch->getOperationsByAlias())
                )];

                return $this->successfulBatch($batch);
            }
        );

        $result = $this->synchronizer([42 => $this->identity(42, 'SKU-1')], $executor)
            ->synchronize($state);

        self::assertSame(ProductSynchronizationResultInterface::STATUS_SUCCESS, $result->getStatus());
        self::assertSame(
            ['productSetTemplate', 'productSetStatus'],
            $fields
        );
        self::assertNotContains('productCreateSimple', $fields);
    }

    public function testNewlyCreatedAssignedProductSkipsBaseUpdateAndContinuesPublication(): void
    {
        $state = $this->createStub(ProductCreationContextInterface::class);
        $state->method('getSku')->willReturn('ERG-123');
        $state->method('getMagentoProductId')->willReturn(42);
        $state->method('getErgonodeSku')->willReturn('ERG-123');
        $state->method('getIdentityMode')->willReturn(ProductIdentityInterface::MODE_ASSIGNED);
        $state->method('getType')->willReturn('simple');
        $state->method('getTemplateCode')->willReturn('default');
        $state->method('getStatuses')->willReturn(['pl_PL' => 'active']);
        $state->method('getValues')->willReturn([]);
        $state->method('getRelations')->willReturn(new ProductRelationState());
        $state->method('isDeleted')->willReturn(false);
        $state->method('wasCreatedInCurrentSynchronization')->willReturn(true);
        $fields = [];
        $executor = $this->createStub(MutationExecutorInterface::class);
        $executor->method('execute')->willReturnCallback(
            function (MutationBatch $batch) use (&$fields): SynchronizationResult {
                $fields = array_map(
                    static fn (MutationOperationInterface $operation): string => $operation->getField(),
                    array_values($batch->getOperationsByAlias())
                );

                return $this->successfulBatch($batch);
            }
        );

        $result = $this->synchronizer([42 => $this->identity(42, 'ERG-123')], $executor)
            ->synchronize($state);

        self::assertSame(ProductSynchronizationResultInterface::STATUS_SUCCESS, $result->getStatus());
        self::assertSame(['productSetStatus'], $fields);
    }

    public function testCreateConflictContinuesAsUpdate(): void
    {
        $state = new ProductState('SKU-1', 'simple', 'default');
        $calls = 0;
        $executor = $this->createStub(MutationExecutorInterface::class);
        $executor->method('execute')->willReturnCallback(
            function (MutationBatch $batch) use (&$calls): SynchronizationResult {
                ++$calls;
                $alias = array_key_first($batch->getOperationsByAlias());
                $operation = $batch->getOperationsByAlias()[$alias];
                if ($operation->getField() === 'productCreateSimple') {
                    return new SynchronizationResult([
                        new MutationResult(
                            MutationResultInterface::STATUS_VALIDATION_FAILURE,
                            $alias,
                            $operation,
                            errors: [['message' => 'Product SKU-1 already exists.']]
                        ),
                    ]);
                }

                return $this->successfulBatch($batch);
            }
        );

        $result = $this->synchronizer([], $executor)->synchronize($state);

        self::assertSame(2, $calls);
        self::assertSame(ProductSynchronizationResultInterface::STATUS_SUCCESS, $result->getStatus());
        self::assertSame(
            ['productCreateSimple', 'productSetTemplate'],
            $this->fields($result->getResults())
        );
        self::assertSame(
            [MutationResultInterface::STATUS_SUCCESS, MutationResultInterface::STATUS_SUCCESS],
            array_map(static fn ($mutation): string => $mutation->getStatus(), $result->getResults())
        );
    }

    public function testOneCreateFailureDoesNotBlockIndependentProduct(): void
    {
        $first = new ProductState('SKU-1', 'simple', 'default');
        $second = new ProductState('SKU-2', 'simple', 'default');
        $executor = $this->createStub(MutationExecutorInterface::class);
        $executor->method('execute')->willReturnCallback(
            static function (MutationBatch $batch): SynchronizationResult {
                $results = [];
                foreach ($batch->getOperationsByAlias() as $alias => $operation) {
                    $status = $operation->getMetadata()['entity_sku'] === 'SKU-1'
                        ? MutationResultInterface::STATUS_VALIDATION_FAILURE
                        : MutationResultInterface::STATUS_SUCCESS;
                    $results[] = new MutationResult(
                        $status,
                        $alias,
                        $operation,
                        errors: $status === MutationResultInterface::STATUS_SUCCESS
                            ? []
                            : [['message' => 'Invalid product data.']]
                    );
                }

                return new SynchronizationResult($results);
            }
        );

        $results = $this->synchronizer([], $executor)->synchronizeBatch([$first, $second]);

        self::assertSame(ProductSynchronizationResultInterface::STATUS_FAILED, $results[0]->getStatus());
        self::assertStringContainsString('Invalid product data.', $results[0]->getMessage());
        self::assertStringContainsString('productCreateSimple', $results[0]->getMessage());
        self::assertStringContainsString('Log reference:', $results[0]->getMessage());
        self::assertSame(ProductSynchronizationResultInterface::STATUS_SUCCESS, $results[1]->getStatus());
    }

    public function testTransientMutationFailureRequiresAttention(): void
    {
        $state = new ProductState('SKU-1', 'simple', 'default');
        $executor = $this->createStub(MutationExecutorInterface::class);
        $executor->method('execute')->willReturnCallback(
            static function (MutationBatch $batch): SynchronizationResult {
                $alias = array_key_first($batch->getOperationsByAlias());
                $operation = $batch->getOperationsByAlias()[$alias];

                return new SynchronizationResult([
                    new MutationResult(
                        MutationResultInterface::STATUS_TRANSIENT_FAILURE,
                        $alias,
                        $operation,
                        errors: [['message' => 'Too many requests.']]
                    ),
                ]);
            }
        );

        $result = $this->synchronizer([], $executor)->synchronize($state);

        self::assertSame(ProductSynchronizationResultInterface::STATUS_ATTENTION, $result->getStatus());
    }

    public function testMissingSharedProductIsRecreatedWithoutRemoteRead(): void
    {
        $state = new ProductState(
            'SKU-1',
            'simple',
            'default',
            statuses: ['pl_PL' => 'active'],
            magentoProductId: 42,
            identityMode: ProductIdentityInterface::MODE_SHARED
        );
        $calls = 0;
        $fields = [];
        $executor = $this->createStub(MutationExecutorInterface::class);
        $executor->method('execute')->willReturnCallback(
            function (MutationBatch $batch) use (&$calls, &$fields): SynchronizationResult {
                ++$calls;
                $results = [];
                foreach ($batch->getOperationsByAlias() as $alias => $operation) {
                    $fields[] = $operation->getField();
                    $missing = $calls === 1;
                    $results[] = new MutationResult(
                        $missing
                            ? MutationResultInterface::STATUS_VALIDATION_FAILURE
                            : MutationResultInterface::STATUS_SUCCESS,
                        $alias,
                        $operation,
                        errors: $missing ? [['extensions' => ['code' => 'NOT_FOUND']]] : []
                    );
                }

                return new SynchronizationResult($results);
            }
        );

        $result = $this->synchronizer([42 => $this->identity(42, 'SKU-1')], $executor)
            ->synchronize($state);

        self::assertSame(ProductSynchronizationResultInterface::STATUS_SUCCESS, $result->getStatus());
        self::assertSame(
            ['productSetTemplate', 'productSetStatus', 'productCreateSimple', 'productSetStatus'],
            $fields
        );
    }

    public function testMappedAssignedProductUpdatesWithoutRemotePreRead(): void
    {
        $state = new ProductState(
            '1000000001',
            'simple',
            'default',
            magentoProductId: 42,
            identityMode: ProductIdentityInterface::MODE_ASSIGNED
        );
        $fields = [];
        $executor = $this->createStub(MutationExecutorInterface::class);
        $executor->method('execute')->willReturnCallback(
            function (MutationBatch $batch) use (&$fields): SynchronizationResult {
                foreach ($batch->getOperationsByAlias() as $operation) {
                    $fields[] = $operation->getField();
                }

                return $this->successfulBatch($batch);
            }
        );

        $result = $this->synchronizer([42 => $this->identity(42, '1000000001')], $executor)
            ->synchronize($state);

        self::assertSame(ProductSynchronizationResultInterface::STATUS_SUCCESS, $result->getStatus());
        self::assertSame(['productSetTemplate'], $fields);
    }

    public function testAssignedProductMissingDuringMutationIsRecreatedWithItsMappedSku(): void
    {
        $state = new ProductState(
            '1000000001',
            'simple',
            'default',
            statuses: ['pl_PL' => 'active'],
            magentoProductId: 42,
            identityMode: ProductIdentityInterface::MODE_ASSIGNED
        );
        $calls = 0;
        $fields = [];
        $executor = $this->createStub(MutationExecutorInterface::class);
        $executor->method('execute')->willReturnCallback(
            function (MutationBatch $batch) use (&$calls, &$fields): SynchronizationResult {
                ++$calls;
                $results = [];
                foreach ($batch->getOperationsByAlias() as $alias => $operation) {
                    $fields[] = $operation->getField();
                    $missing = $calls === 1;
                    $results[] = new MutationResult(
                        $missing
                            ? MutationResultInterface::STATUS_VALIDATION_FAILURE
                            : MutationResultInterface::STATUS_SUCCESS,
                        $alias,
                        $operation,
                        errors: $missing ? [['extensions' => ['code' => 'NOT_FOUND']]] : []
                    );
                }

                return new SynchronizationResult($results);
            }
        );

        $result = $this->synchronizer([42 => $this->identity(42, '1000000001')], $executor)
            ->synchronize($state);

        self::assertSame(ProductSynchronizationResultInterface::STATUS_SUCCESS, $result->getStatus());
        self::assertSame(
            ['productSetTemplate', 'productSetStatus', 'productCreateSimple', 'productSetStatus'],
            $fields
        );
    }

    public function testOperationStreamUsesPlannerCapacityBatchesOfAtMostFifty(): void
    {
        $states = [];
        for ($index = 1; $index <= 51; ++$index) {
            $states[] = new ProductState('SKU-' . $index, 'simple', 'default');
        }
        $planner = $this->createStub(MutationBatchPlannerInterface::class);
        $planner->method('plan')->willReturnCallback(function (array $operations): array {
            return array_map($this->batch(...), array_chunk($operations, 50));
        });
        $dispatchSizes = [];
        $executor = $this->createStub(MutationExecutorInterface::class);
        $executor->method('execute')->willReturnCallback(
            function (MutationBatch $batch) use (&$dispatchSizes): SynchronizationResult {
                $dispatchSizes[] = count($batch->getOperationsByAlias());

                return $this->successfulBatch($batch);
            }
        );

        $results = $this->synchronizer([], $executor, $planner)->synchronizeBatch($states);

        self::assertCount(51, $results);
        self::assertSame([50, 1], $dispatchSizes);
    }

    public function testNumericSkuRemainsAStringAcrossLocalCorrelation(): void
    {
        $state = new ProductState('1000000001', 'simple', 'default');
        $executor = $this->createStub(MutationExecutorInterface::class);
        $executor->method('execute')->willReturnCallback($this->successfulBatch(...));

        $result = $this->synchronizer([], $executor)->synchronize($state);

        self::assertSame('1000000001', $result->getSku());
        self::assertSame('1000000001', $result->getResults()[0]->getOperation()->getMetadata()['entity_sku']);
    }

    public function testExistingGroupedChildFallsBackToQuantityUpdate(): void
    {
        $state = new ProductState(
            'GROUP',
            'grouping',
            'default',
            relations: new ProductRelationState(groupedChildren: ['CHILD' => 3]),
            magentoProductId: 42
        );
        $fields = [];
        $executor = $this->createStub(MutationExecutorInterface::class);
        $executor->method('execute')->willReturnCallback(
            function (MutationBatch $batch) use (&$fields): SynchronizationResult {
                $results = [];
                foreach ($batch->getOperationsByAlias() as $alias => $operation) {
                    $fields[] = $operation->getField();
                    if ($operation->getField() === 'productGroupingAddChild') {
                        $results[] = new MutationResult(
                            MutationResultInterface::STATUS_VALIDATION_FAILURE,
                            $alias,
                            $operation,
                            errors: [['extensions' => ['code' => 'ALREADY_EXISTS']]]
                        );
                        continue;
                    }
                    $results[] = new MutationResult(
                        MutationResultInterface::STATUS_SUCCESS,
                        $alias,
                        $operation
                    );
                }

                return new SynchronizationResult($results);
            }
        );

        $result = $this->synchronizer([42 => $this->identity(42, 'GROUP')], $executor)
            ->synchronize($state);

        self::assertSame(ProductSynchronizationResultInterface::STATUS_SUCCESS, $result->getStatus());
        self::assertSame(
            ['productSetTemplate', 'productGroupingAddChild', 'productGroupingSetChildQuantity'],
            $fields
        );
    }

    public function testNewProductTimeoutDoesNotRecreateItAndOtherProductsContinue(): void
    {
        $state = $this->newProductState();
        $other = new ProductState('OTHER', 'simple', 'default', magentoProductId: 43);
        $fields = [];
        $executor = $this->createStub(MutationExecutorInterface::class);
        $executor->method('execute')->willReturnCallback(function (MutationBatch $batch) use (&$fields) {
            $results = [];
            foreach ($batch->getOperationsByAlias() as $alias => $operation) {
                $fields[] = $operation->getField();
                $failed = $operation->getMetadata()['entity_sku'] === 'ERG-NEW';
                $results[] = new MutationResult(
                    $failed
                        ? MutationResultInterface::STATUS_VALIDATION_FAILURE
                        : MutationResultInterface::STATUS_SUCCESS,
                    $alias,
                    $operation,
                    errors: $failed ? [['extensions' => ['code' => 'NOT_FOUND']]] : []
                );
            }
            return new SynchronizationResult($results);
        });
        $visibility = $this->createMock(NewProductVisibility::class);
        $visibility->expects(self::exactly(6))->method('load')->willReturn([]);

        $results = $this->synchronizer(
            [42 => $this->identity(42, 'ERG-NEW'), 43 => $this->identity(43, 'OTHER')],
            $executor,
            visibility: $visibility
        )->synchronizeBatch([$state, $other]);

        self::assertSame(ProductSynchronizationResultInterface::STATUS_ATTENTION, $results[0]->getStatus());
        self::assertStringContainsString('SKU mapping was retained', $results[0]->getMessage());
        self::assertSame(ProductSynchronizationResultInterface::STATUS_SUCCESS, $results[1]->getStatus());
        self::assertNotContains('productCreateSimple', $fields);
    }

    public function testNewProductResumesFollowingMutationBatchesAfterVisibilityRecovery(): void
    {
        $state = $this->newProductState();
        $calls = 0;
        $executor = $this->createStub(MutationExecutorInterface::class);
        $executor->method('execute')->willReturnCallback(function (MutationBatch $batch) use (&$calls) {
            ++$calls;
            if ($calls === 1) {
                $alias = array_key_first($batch->getOperationsByAlias());
                return new SynchronizationResult([new MutationResult(
                    MutationResultInterface::STATUS_VALIDATION_FAILURE,
                    $alias,
                    $batch->getOperationsByAlias()[$alias],
                    errors: [['message' => 'An unknown error occurred.']]
                )]);
            }
            return $this->successfulBatch($batch);
        });
        $planner = $this->createStub(MutationBatchPlannerInterface::class);
        $planner->method('plan')->willReturnCallback(fn (array $operations): array => array_map(
            fn ($operation): MutationBatch => $this->batch([$operation]),
            $operations
        ));
        $visibility = $this->createMock(NewProductVisibility::class);
        $visibility->expects(self::exactly(2))->method('load')->willReturnOnConsecutiveCalls([], ['ERG-NEW']);

        $result = $this->synchronizer(
            [42 => $this->identity(42, 'ERG-NEW')],
            $executor,
            $planner,
            $visibility
        )->synchronize($state);

        self::assertSame(3, $calls);
        self::assertSame(ProductSynchronizationResultInterface::STATUS_SUCCESS, $result->getStatus());
        self::assertSame(
            [2, 1],
            array_map(static fn ($item): int => $item->getAttempts(), $result->getResults())
        );
    }

    public function testOperationsKeepCompleteProductOrderAcrossTheBatch(): void
    {
        $first = new ProductState('FIRST', 'simple', 'default', statuses: ['en_GB' => 'enabled'], magentoProductId: 42);
        $second = new ProductState(
            'SECOND',
            'simple',
            'default',
            statuses: ['en_GB' => 'enabled'],
            magentoProductId: 43
        );
        $order = [];
        $executor = $this->createStub(MutationExecutorInterface::class);
        $executor->method('execute')->willReturnCallback(function (MutationBatch $batch) use (&$order) {
            foreach ($batch->getOperationsByAlias() as $operation) {
                $order[] = [$operation->getMetadata()['entity_sku'], $operation->getField()];
            }
            return $this->successfulBatch($batch);
        });
        $visibility = $this->createMock(NewProductVisibility::class);
        $visibility->expects(self::never())->method('load');

        $this->synchronizer(
            [42 => $this->identity(42, 'FIRST'), 43 => $this->identity(43, 'SECOND')],
            $executor,
            visibility: $visibility
        )->synchronizeBatch([$first, $second]);

        self::assertSame([
            ['FIRST', 'productSetTemplate'], ['FIRST', 'productSetStatus'],
            ['SECOND', 'productSetTemplate'], ['SECOND', 'productSetStatus'],
        ], $order);
    }

    public function testClearingAnAbsentValueOnANewProductRetainsItsIdempotentBehavior(): void
    {
        $state = $this->newProductState([
            new ProductAttributeValue('color', 'text', [], clearedLanguageCodes: ['en_GB']),
        ]);
        $visibility = $this->createMock(NewProductVisibility::class);
        $visibility->expects(self::never())->method('load');
        $executor = $this->createStub(MutationExecutorInterface::class);
        $executor->method('execute')->willReturnCallback(function (MutationBatch $batch) {
            $results = [];
            foreach ($batch->getOperationsByAlias() as $alias => $operation) {
                $clear = str_starts_with($operation->getMetadata()['operation_key'], 'value_delete:');
                $results[] = new MutationResult(
                    $clear
                        ? MutationResultInterface::STATUS_VALIDATION_FAILURE
                        : MutationResultInterface::STATUS_SUCCESS,
                    $alias,
                    $operation,
                    errors: $clear ? [['extensions' => ['code' => 'NOT_FOUND']]] : []
                );
            }
            return new SynchronizationResult($results);
        });

        $result = $this->synchronizer(
            [42 => $this->identity(42, 'ERG-NEW')],
            $executor,
            visibility: $visibility
        )->synchronize($state);

        self::assertSame(ProductSynchronizationResultInterface::STATUS_SUCCESS, $result->getStatus());
        self::assertCount(3, $result->getResults());
    }

    /** @param ProductAttributeValue[] $values */
    private function newProductState(array $values = []): ProductCreationContextInterface
    {
        $state = $this->createStub(ProductCreationContextInterface::class);
        $state->method('getSku')->willReturn('ERG-NEW');
        $state->method('getMagentoProductId')->willReturn(42);
        $state->method('getErgonodeSku')->willReturn('ERG-NEW');
        $state->method('getIdentityMode')->willReturn(ProductIdentityInterface::MODE_ASSIGNED);
        $state->method('getType')->willReturn('simple');
        $state->method('getTemplateCode')->willReturn('default');
        $state->method('getStatuses')->willReturn(['en_GB' => 'enabled', 'pl_PL' => 'enabled']);
        $state->method('getValues')->willReturn($values);
        $state->method('getRelations')->willReturn(new ProductRelationState());
        $state->method('wasCreatedInCurrentSynchronization')->willReturn(true);
        return $state;
    }

    /** @param array<int, ProductIdentityInterface> $identities */
    private function synchronizer(
        array $identities,
        MutationExecutorInterface $executor,
        ?MutationBatchPlannerInterface $batchPlanner = null,
        ?NewProductVisibility $visibility = null,
        ?ProductIdentityRegistryInterface $registry = null,
        ?RemoteProductPublicationStateLoader $remoteState = null
    ): ProductSynchronizer {
        if ($registry === null) {
            $registry = $this->createStub(ProductIdentityRegistryInterface::class);
            $registry->method('getIdentitiesByProductIds')->willReturn($identities);
        }
        $visibility ??= $this->createStub(NewProductVisibility::class);
        return new ProductSynchronizer(
            $registry,
            new ProductPublicationMutationPlanner(
                new ProductMutationFactory(),
                $remoteState ?? $this->createStub(RemoteProductPublicationStateLoader::class)
            ),
            new ProductMutationFailureClassifier(),
            $batchPlanner ?? $this->batchPlanner(),
            $executor,
            new ProductMutationFailureReporter(new NullLogger()),
            new NewProductMutationRecovery(
                $visibility,
                $batchPlanner ?? $this->batchPlanner(),
                $executor,
                new ProductMutationFailureClassifier()
            ),
            $visibility
        );
    }

    private function batchPlanner(): MutationBatchPlannerInterface
    {
        $planner = $this->createStub(MutationBatchPlannerInterface::class);
        $planner->method('plan')->willReturnCallback(function (array $operations): array {
            return [$this->batch($operations)];
        });

        return $planner;
    }

    private function successfulBatch(MutationBatch $batch): SynchronizationResult
    {
        $results = [];
        foreach ($batch->getOperationsByAlias() as $alias => $operation) {
            $results[] = new MutationResult(MutationResultInterface::STATUS_SUCCESS, $alias, $operation);
        }

        return new SynchronizationResult($results);
    }

    /** @param MutationOperationInterface[] $operations */
    private function batch(array $operations): MutationBatch
    {
        $indexed = [];
        foreach ($operations as $index => $operation) {
            $indexed['operation' . $index] = $operation;
        }

        return new MutationBatch('mutation Test { __typename }', [], $indexed);
    }

    private function identity(
        int $productId,
        string $sku,
        string $mode = ProductIdentityInterface::MODE_SHARED
    ): ProductIdentityInterface {
        $identity = $this->createStub(ProductIdentityInterface::class);
        $identity->method('getProductId')->willReturn($productId);
        $identity->method('getErgonodeSku')->willReturn($sku);
        $identity->method('getIdentityMode')->willReturn($mode);

        return $identity;
    }

    /** @param MutationResultInterface[] $results @return string[] */
    private function fields(array $results): array
    {
        return array_map(
            static fn (MutationResultInterface $result): string => $result->getOperation()->getField(),
            $results
        );
    }
}
