<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryPublisher\Test\Unit\Model\Sync;

use Ergonode\Core\Api\GraphQlWriteScopeQueryClientInterface;
use Ergonode\ProductCategoryPublisher\Model\Config\CategoryPublicationConfig;
use Ergonode\ProductCategoryPublisher\Model\Data\ProductCategoryState;
use Ergonode\ProductCategoryPublisher\Model\GraphQl\ProductCategoryMutationBuilder;
use Ergonode\ProductCategoryPublisher\Model\GraphQl\RemoteProductCategoryStateLoader;
use Ergonode\ProductCategoryPublisher\Model\Sync\ProductCategoryPublicationPolicy;
use Ergonode\ProductCategoryPublisher\Model\Sync\ProductCategoryPublicationSynchronizer;
use Ergonode\ProductPublisher\Api\Data\ProductSynchronizationResultInterface;
use Ergonode\ProductPublisher\Model\Data\ProductState;
use Ergonode\ProductPublisher\Model\Data\ProductSynchronizationResult;
use Ergonode\Publisher\Api\Data\MutationResultInterface;
use Ergonode\Publisher\Api\MutationBatchPlannerInterface;
use Ergonode\Publisher\Api\MutationExecutorInterface;
use Ergonode\Publisher\Model\Data\MutationBatch;
use Ergonode\Publisher\Model\Data\MutationResult;
use Ergonode\Publisher\Model\Data\SynchronizationResult;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use TypeError;

class ProductCategoryPublicationSynchronizerTest extends TestCase
{
    #[DataProvider('skus')]
    public function testMatchModeAddsMissingAndRemovesRemoteOnlyCodes(string $sku): void
    {
        $state = new ProductCategoryState(
            new ProductState($sku, 'simple', 'default'),
            ['chairs', 'new-category'],
            true
        );
        $client = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $client->expects(self::once())->method('queryWriteScope')
            ->with(self::isType('string'), ['sku_0' => $sku, 'after_0' => null])
            ->willReturn(['product_0' => [
                'sku' => $sku,
                'categoryList' => [
                    'edges' => [['node' => ['code' => 'chairs']], ['node' => ['code' => 'remote-only']]],
                    'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                ],
            ]]);
        $loader = new RemoteProductCategoryStateLoader($client);
        $config = $this->createStub(CategoryPublicationConfig::class);
        $config->method('shouldRemoveMissingCategories')->willReturn(true);
        $planner = $this->createStub(MutationBatchPlannerInterface::class);
        $planner->method('plan')->willReturnCallback(static function (array $operations): array {
            $indexed = [];
            foreach ($operations as $index => $operation) {
                $indexed['operation' . $index] = $operation;
            }

            return [new MutationBatch('mutation Test { __typename }', [], $indexed)];
        });
        $executor = $this->createStub(MutationExecutorInterface::class);
        $executor->method('execute')->willReturnCallback(static function (MutationBatch $batch): SynchronizationResult {
            $results = [];
            foreach ($batch->getOperationsByAlias() as $alias => $operation) {
                $results[] = new MutationResult(MutationResultInterface::STATUS_SUCCESS, $alias, $operation);
            }

            return new SynchronizationResult($results);
        });
        $base = new ProductSynchronizationResult(
            $sku,
            ProductSynchronizationResultInterface::STATUS_SUCCESS
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');
        $result = (new ProductCategoryPublicationSynchronizer(
            $loader,
            new ProductCategoryPublicationPolicy($config),
            new ProductCategoryMutationBuilder(),
            $planner,
            $executor,
            $logger
        ))->synchronize([$state], [$base])[0];

        self::assertSame(ProductSynchronizationResultInterface::STATUS_SUCCESS, $result->getStatus());
        self::assertSame($sku, $result->getSku());
        foreach ($result->getResults() as $mutation) {
            self::assertSame($sku, $mutation->getOperation()->getVariables()['input']->getValue()['sku']);
            self::assertSame($sku, $mutation->getOperation()->getMetadata()['entity_sku']);
        }
        self::assertSame(
            ['productAddCategories', 'productRemoveCategories'],
            array_map(
                static fn (MutationResultInterface $item): string => $item->getOperation()->getField(),
                $result->getResults()
            )
        );
        self::assertSame(
            ['new-category'],
            $result->getResults()[0]->getOperation()->getVariables()['input']->getValue()['categoryCodes']
        );
        self::assertSame(
            ['remote-only'],
            $result->getResults()[1]->getOperation()->getVariables()['input']->getValue()['categoryCodes']
        );
    }

    public function testReadFailurePreservesBaseResultsAndNeverSendsCategoryMutations(): void
    {
        $states = [
            new ProductCategoryState(new ProductState('1000000140', 'simple', 'default'), ['a'], true),
            new ProductCategoryState(new ProductState('01000000140', 'simple', 'default'), ['b'], true),
            new ProductCategoryState(new ProductState('failed', 'simple', 'default'), ['c'], true),
            new ProductState('plain', 'simple', 'default'),
            new ProductCategoryState(new ProductState('deleted', 'simple', 'default', deleted: true), [], true),
        ];
        $baseMutation = $this->createStub(MutationResultInterface::class);
        $baseResults = [
            new ProductSynchronizationResult('1000000140', 'success', [$baseMutation]),
            new ProductSynchronizationResult('01000000140', 'noop'),
            new ProductSynchronizationResult('failed', 'failed', message: 'Base failed.'),
            new ProductSynchronizationResult('plain', 'success'),
            new ProductSynchronizationResult('deleted', 'success'),
        ];
        $failure = new TypeError('Private token must not reach logs or the UI.');
        $loader = $this->createMock(RemoteProductCategoryStateLoader::class);
        $loader->expects(self::once())->method('load')->with(['1000000140', '01000000140'])
            ->willThrowException($failure);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')
            ->with('Product category state read failed after base publication.', [
                'skus' => ['1000000140', '01000000140'],
                'stage' => 'category_state_read',
                'exception_class' => TypeError::class,
                'source_file' => $failure->getFile(),
                'source_line' => $failure->getLine(),
            ]);
        $planner = $this->createMock(MutationBatchPlannerInterface::class);
        $planner->expects(self::never())->method('plan');
        $executor = $this->createMock(MutationExecutorInterface::class);
        $executor->expects(self::never())->method('execute');
        $actual = (new ProductCategoryPublicationSynchronizer(
            $loader,
            new ProductCategoryPublicationPolicy($this->createStub(CategoryPublicationConfig::class)),
            new ProductCategoryMutationBuilder(),
            $planner,
            $executor,
            $logger
        ))->synchronize($states, $baseResults);

        self::assertCount(5, $actual);
        self::assertSame([$baseMutation], $actual[0]->getResults());
        foreach ([0, 1] as $index) {
            self::assertSame($baseResults[$index]->getSku(), $actual[$index]->getSku());
            self::assertSame('failed', $actual[$index]->getStatus());
            self::assertStringContainsString('base publication stage completed', $actual[$index]->getMessage());
            self::assertStringContainsString('Category assignments were not sent', $actual[$index]->getMessage());
            self::assertStringNotContainsString('Private token', $actual[$index]->getMessage());
        }
        self::assertSame($baseResults[2], $actual[2]);
        self::assertSame($baseResults[3], $actual[3]);
        self::assertSame($baseResults[4], $actual[4]);
    }

    #[DataProvider('publicationModes')]
    public function testIncompleteRemotePageNeverSendsCategoryMutations(bool $matchMode): void
    {
        $state = new ProductCategoryState(
            new ProductState('SKU-1', 'simple', 'default'),
            ['desired'],
            true
        );
        $baseMutation = $this->createStub(MutationResultInterface::class);
        $base = new ProductSynchronizationResult('SKU-1', 'success', [$baseMutation]);
        $client = $this->createStub(GraphQlWriteScopeQueryClientInterface::class);
        $client->method('queryWriteScope')->willReturn(['product_0' => [
            'sku' => 'SKU-1',
            'categoryList' => [
                'edges' => [['node' => ['code' => 'remote-only']]],
                'pageInfo' => null,
            ],
        ]]);
        $config = $this->createStub(CategoryPublicationConfig::class);
        $config->method('shouldRemoveMissingCategories')->willReturn($matchMode);
        $planner = $this->createMock(MutationBatchPlannerInterface::class);
        $planner->expects(self::never())->method('plan');
        $executor = $this->createMock(MutationExecutorInterface::class);
        $executor->expects(self::never())->method('execute');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')
            ->with('Product category state read failed after base publication.', self::isType('array'));

        $result = (new ProductCategoryPublicationSynchronizer(
            new RemoteProductCategoryStateLoader($client),
            new ProductCategoryPublicationPolicy($config),
            new ProductCategoryMutationBuilder(),
            $planner,
            $executor,
            $logger
        ))->synchronize([$state], [$base])[0];

        self::assertSame('failed', $result->getStatus());
        self::assertSame([$baseMutation], $result->getResults());
        self::assertStringContainsString('Category assignments were not sent', $result->getMessage());
    }

    /** @return array<string, array{bool}> */
    public static function publicationModes(): array
    {
        return ['keep' => [false], 'match' => [true]];
    }

    /** @return array<string, array{string}> */
    public static function skus(): array
    {
        return ['text' => ['SKU-1'], 'numeric' => ['1000000140'], 'leading zeros' => ['00140'], 'zero' => ['0']];
    }
}
