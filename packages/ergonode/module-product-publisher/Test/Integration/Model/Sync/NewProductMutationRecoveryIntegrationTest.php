<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\Integration\Model\Sync;

use Ergonode\ProductPublisher\Api\ProductBatchSynchronizerInterface;
use Ergonode\ProductPublisher\Model\GraphQl\ProductMutationFactory;
use Ergonode\ProductPublisher\Model\Sync\NewProductMutationRecovery;
use Ergonode\ProductPublisher\Model\Sync\NewProductVisibility;
use Ergonode\Publisher\Api\Data\MutationResultInterface;
use Ergonode\Publisher\Api\MutationExecutorInterface;
use Ergonode\Publisher\Model\Data\MutationResult;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Ergonode\Core\Api\GraphQlWriteScopeQueryClientInterface;
use Ergonode\ProductPublisher\Model\Data\ProductState;
use Ergonode\ProductPublisher\Model\GraphQl\RemoteProductPublicationStateLoader;
use Ergonode\ProductPublisher\Model\Sync\ProductPublicationMutationPlanner;

use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Ergonode\ProductPublisher\Api\ProductIdentityRegistryInterface;
use Ergonode\ProductPublisher\Api\Data\ProductSynchronizationResultInterface;
use Ergonode\ProductPublisher\Model\Data\ProductAttributeValue;
use Ergonode\ProductPublisher\Model\Sync\ProductSynchronizer;
use Ergonode\Publisher\Model\Data\MutationBatch;
use Ergonode\Publisher\Model\Data\SynchronizationResult;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;

class NewProductMutationRecoveryIntegrationTest extends TestCase
{
    #[DbIsolation(true)]
    #[DataFixture(ProductFixture::class, ['sku' => 'post-create-comparison'], as: 'product')]
    public function testPostCreateComparisonFollowsDatabaseBindingAndKeepsItAfterDeleteFailure(): void
    {
        $manager = Bootstrap::getObjectManager();
        $product = DataFixtureStorageManager::getStorage()->get('product');
        self::assertInstanceOf(Product::class, $product);
        $productId = (int)$product->getId();
        $registry = $manager->get(ProductIdentityRegistryInterface::class);
        $calls = 0;
        $client = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $client->expects(self::exactly(2))->method('queryWriteScope')->willReturnCallback(
            static function () use (&$calls, $registry, $productId): array {
                if (++$calls === 1) {
                    return ['product_0' => null];
                }
                $identity = $registry->getIdentitiesByProductIds([$productId])[$productId];
                self::assertSame('POST-CREATE', $identity->getErgonodeSku());
                return ['product_0' => [
                    'sku' => 'POST-CREATE', 'template' => ['code' => 'default'],
                    'attributeList' => [
                        'edges' => [['node' => [
                            'attribute' => ['code' => 'title'], 'translations' => [['language' => 'pl_PL']],
                        ]]],
                        'pageInfo' => ['hasNextPage' => false],
                    ],
                ]];
            }
        );
        $fields = [];
        $executor = $this->createMock(MutationExecutorInterface::class);
        $executor->expects(self::exactly(2))->method('execute')->willReturnCallback(
            static function (MutationBatch $batch) use (&$fields): SynchronizationResult {
                $results = [];
                foreach ($batch->getOperationsByAlias() as $alias => $operation) {
                    $fields[] = $operation->getField();
                    $results[] = new MutationResult(
                        $operation->getField() === 'productCreateSimple'
                            ? MutationResultInterface::STATUS_SUCCESS
                            : MutationResultInterface::STATUS_PERMANENT_FAILURE,
                        $alias,
                        $operation
                    );
                }
                return new SynchronizationResult($results);
            }
        );
        $reader = $manager->create(RemoteProductPublicationStateLoader::class, ['client' => $client]);
        $synchronizer = $manager->create(ProductSynchronizer::class, [
            'executor' => $executor,
            'visibility' => $manager->create(NewProductVisibility::class, ['client' => $client]),
            'mutationPlanner' => $manager->create(ProductPublicationMutationPlanner::class, ['remoteState' => $reader]),
        ]);
        $result = $synchronizer->synchronize(new ProductState(
            'POST-CREATE',
            'simple',
            'default',
            values: [new ProductAttributeValue('title', 'text', [], clearedLanguageCodes: ['pl_PL', 'en_GB'])],
            magentoProductId: $productId,
            identityMode: ProductIdentityInterface::MODE_MAPPED,
            ergonodeSku: 'POST-CREATE'
        ));
        self::assertSame(ProductSynchronizationResultInterface::STATUS_FAILED, $result->getStatus());
        self::assertSame(['productCreateSimple', 'productDeleteAttributeValueTranslations'], $fields);
        $identity = $registry->getIdentitiesByProductIds([$productId])[$productId];
        self::assertSame('POST-CREATE', $identity->getErgonodeSku());
        self::assertSame(ProductIdentityInterface::MODE_MAPPED, $identity->getIdentityMode());
    }

    public function testMagentoResolvesComparisonReaderAndPlanner(): void
    {
        $manager = Bootstrap::getObjectManager();
        $client = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $client->expects(self::once())->method('queryWriteScope')->willReturn([
            'product_0' => [
                'sku' => 'existing', 'template' => ['code' => 'default'],
                'attributeList' => ['edges' => [], 'pageInfo' => ['hasNextPage' => false]],
            ],
        ]);
        $reader = $manager->create(RemoteProductPublicationStateLoader::class, ['client' => $client]);
        $planner = $manager->create(ProductPublicationMutationPlanner::class, ['remoteState' => $reader]);
        self::assertSame([], $planner->operations(
            ['existing' => new ProductState('existing', 'simple', 'default')],
            ['existing' => true]
        ));
    }

    public function testMagentoResolvesRecoveryAndItsNumericXmlArguments(): void
    {
        $manager = Bootstrap::getObjectManager();
        self::assertInstanceOf(
            ProductBatchSynchronizerInterface::class,
            $manager->get(ProductBatchSynchronizerInterface::class)
        );
        $delays = [];
        $visibility = $this->createMock(NewProductVisibility::class);
        $visibility->expects(self::exactly(6))->method('load')->willReturnCallback(
            static function (array $skus, int $delay) use (&$delays): array {
                self::assertSame(['new-product'], $skus);
                $delays[] = $delay;
                return [];
            }
        );
        $executor = $this->createMock(MutationExecutorInterface::class);
        $executor->expects(self::never())->method('execute');
        $recovery = $manager->create(NewProductMutationRecovery::class, [
            'visibility' => $visibility,
            'executor' => $executor,
        ]);
        $result = new MutationResult(
            MutationResultInterface::STATUS_VALIDATION_FAILURE,
            'status',
            (new ProductMutationFactory())->setStatus('new-product', 'en_GB', 'enabled'),
            errors: [['message' => 'An unknown error occurred.']]
        );

        $outcome = $recovery->recover([$result], ['new-product']);

        self::assertSame([0, 100, 200, 300, 500, 800], $delays);
        self::assertSame(MutationResultInterface::STATUS_UNRESOLVED, $outcome[0]->getStatus());
    }
}
