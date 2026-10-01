<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\Sync;

use Ergonode\Product\Api\ProductIdentityModeProviderInterface;
use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Ergonode\ProductPublisher\Api\Data\ProductAttributeValueClearIntentInterface;
use Ergonode\ProductPublisher\Api\Data\ProductCreationContextInterface;
use Ergonode\ProductPublisher\Api\Data\ProductStateInterface;
use Ergonode\ProductPublisher\Api\Data\ProductSynchronizationResultInterface;
use Ergonode\ProductPublisher\Api\ProductIdentityRegistryInterface;
use Ergonode\ProductPublisher\Api\ProductPreparedStateDecoratorInterface;
use Ergonode\ProductPublisher\Api\ProductPublicationProductResolverInterface;
use Ergonode\ProductPublisher\Model\Data\ProductAttributeValue;
use Ergonode\ProductPublisher\Model\Data\ProductRelationState;
use Ergonode\ProductPublisher\Model\GraphQl\ProductMutationFactory;
use Ergonode\ProductPublisher\Model\Sync\ProductMutationFailureClassifier;
use Ergonode\Publisher\Api\Data\MutationResultInterface;
use Ergonode\Publisher\Api\MutationBatchPlannerInterface;
use Ergonode\Publisher\Api\MutationExecutorInterface;
use Magento\Framework\Exception\LocalizedException;

class ProductIdentityCoordinator
{
    public function __construct(
        private readonly ProductIdentityRegistryInterface $identityRegistry,
        private readonly ProductIdentityModeProviderInterface $identityModeProvider,
        private readonly ProductMutationFactory $mutationFactory,
        private readonly MutationBatchPlannerInterface $batchPlanner,
        private readonly MutationExecutorInterface $mutationExecutor,
        private readonly ProductPublicationProductResolverInterface $productResolver,
        private readonly ProductMutationFailureClassifier $failureClassifier,
        private readonly ProductMutationFailureReporter $failureReporter,
        /** @var ProductPreparedStateDecoratorInterface[] */
        private readonly array $stateDecorators = []
    ) {
    }

    /** @param array<string, ProductStateInterface> $products */
    public function prepare(array $products): ProductIdentityPreparationResult
    {
        $resolved = [];
        $failures = [];
        $assigned = [];
        foreach ($products as $magentoSku => $product) {
            try {
                $this->identityModeProvider->assertModeAvailable($product->getIdentityMode());
            } catch (LocalizedException $exception) {
                $failures[$magentoSku] = [
                    'status' => ProductSynchronizationResultInterface::STATUS_BLOCKED_REFERENCE,
                    'message' => $exception->getMessage(),
                ];
                continue;
            }
            $remoteSku = $product->getErgonodeSku();
            if ($remoteSku !== null) {
                $resolved[$magentoSku] = $this->remoteState($product, $remoteSku, [], new ProductRelationState());
                continue;
            }
            if ($product->getIdentityMode() !== ProductIdentityInterface::MODE_ASSIGNED
                || $product->getMagentoProductId() === null
            ) {
                $failures[$magentoSku] = [
                    'status' => ProductSynchronizationResultInterface::STATUS_LOCAL_WARNING,
                    'message' => (string)__(
                        'Product "%1" has no resolvable Ergonode identity.',
                        $product->getSku()
                    ),
                ];
                continue;
            }
            $assigned[$product->getMagentoProductId()] = [
                'magento_sku' => (string)$magentoSku,
                'product' => $product,
            ];
        }

        if ($assigned !== []) {
            $this->resolveAssigned($assigned, $resolved, $failures);
        }

        foreach ($resolved as $magentoSku => $product) {
            try {
                $resolved[$magentoSku] = $this->decoratePreparedState(
                    $product,
                    $this->translateReferences($product, $products)
                );
            } catch (LocalizedException $exception) {
                unset($resolved[$magentoSku]);
                $failures[$magentoSku] = [
                    'status' => ProductSynchronizationResultInterface::STATUS_BLOCKED_REFERENCE,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return new ProductIdentityPreparationResult($resolved, $failures);
    }

    /**
     * @param array<int, array{magento_sku: string, product: ProductStateInterface}> $assigned
     * @param array<string, ProductStateInterface> $resolved
     * @param array<string, array{status: string, message: string}> $failures
     */
    private function resolveAssigned(array $assigned, array &$resolved, array &$failures): void
    {
        $bindings = $this->createAssignedBatch($assigned, $failures);
        if ($bindings === []) {
            return;
        }

        try {
            $this->identityRegistry->bindAssignedBatch($bindings);
        } catch (LocalizedException) {
            foreach (array_keys($bindings) as $productId) {
                $magentoSku = $assigned[$productId]['magento_sku'];
                $failures[$magentoSku] = [
                    'status' => ProductSynchronizationResultInterface::STATUS_ATTENTION,
                    'message' => (string)__(
                        'Ergonode created native SKU "%1", but Magento could not save its identity binding. '
                        . 'Reconcile the binding before publishing again.',
                        $bindings[$productId]
                    ),
                ];
            }
            return;
        }
        foreach ($bindings as $productId => $remoteSku) {
            $item = $assigned[$productId];
            $resolved[$item['magento_sku']] = $this->remoteState(
                $item['product'],
                $remoteSku,
                [],
                new ProductRelationState(),
                true
            );
        }
    }

    /**
     * @param array<int, array{magento_sku: string, product: ProductStateInterface}> $assigned
     * @param array<string, array{status: string, message: string}> $failures
     * @return array<int, string>
     */
    private function createAssignedBatch(array $assigned, array &$failures): array
    {
        if ($assigned === []) {
            return [];
        }
        $operations = array_map(
            fn (array $item) => $this->mutationFactory->createWithAssignedSku($item['product']),
            $assigned
        );
        $pending = array_fill_keys(array_keys($assigned), true);
        $bindings = [];
        foreach ($this->batchPlanner->plan($operations) as $batch) {
            foreach ($this->mutationExecutor->execute($batch)->getResults() as $result) {
                $productId = (int)($result->getOperation()->getMetadata()['magento_product_id'] ?? 0);
                if (!isset($pending[$productId])) {
                    throw new LocalizedException(__(
                        'Assigned SKU creation returned invalid product correlation metadata.'
                    ));
                }
                unset($pending[$productId]);
                $item = $assigned[$productId];
                $failure = $this->assignedCreateFailure($result, $item['product']);
                if ($failure !== null) {
                    $failures[$item['magento_sku']] = $failure;
                    continue;
                }
                $data = is_array($result->getData()) ? $result->getData() : [];
                $remoteSku = is_array($data['product'] ?? null)
                    ? trim((string)($data['product']['sku'] ?? ''))
                    : '';
                if ($remoteSku === '') {
                    $failures[$item['magento_sku']] = $this->missingAssignedSkuFailure($item['product']);
                    continue;
                }
                $bindings[$productId] = $remoteSku;
            }
        }
        foreach (array_keys($pending) as $productId) {
            $item = $assigned[$productId];
            $failures[$item['magento_sku']] = [
                'status' => ProductSynchronizationResultInterface::STATUS_ATTENTION,
                'message' => (string)__(
                    'Ergonode returned no correlated result for assigned SKU creation. The product may exist '
                    . 'without a Magento identity binding; verify Ergonode independently before retrying.'
                ),
            ];
        }

        return $bindings;
    }

    /** @return array{status: string, message: string}|null */
    private function assignedCreateFailure(
        MutationResultInterface $result,
        ProductStateInterface $product
    ): ?array {
        if ($this->failureClassifier->isAssignedCreateConflict($result)) {
            return [
                'status' => ProductSynchronizationResultInterface::STATUS_ATTENTION,
                'message' => (string)__(
                    'Ergonode reports that an assigned-SKU product may already exist for Magento SKU "%1", '
                    . 'but its native SKU is unknown. Creation does not write the Magento SKU attribute. '
                    . 'Verify the remote product independently and reconcile its native SKU binding before retrying.',
                    $product->getSku()
                ),
            ];
        }
        if (in_array($result->getStatus(), [
            MutationResultInterface::STATUS_TRANSIENT_FAILURE,
            MutationResultInterface::STATUS_UNRESOLVED,
        ], true)) {
            return [
                'status' => ProductSynchronizationResultInterface::STATUS_ATTENTION,
                'message' => (string)__(
                    'Ergonode product creation may have succeeded for Magento SKU "%1", but no native SKU '
                    . 'was returned. Creation does not write the Magento SKU attribute. Verify the remote '
                    . 'product independently and reconcile its native SKU binding before retrying.',
                    $product->getSku()
                ),
            ];
        }
        if ($result->getStatus() !== MutationResultInterface::STATUS_SUCCESS) {
            return [
                'status' => ProductSynchronizationResultInterface::STATUS_FAILED,
                'message' => $this->failureReporter->describe($result),
            ];
        }

        return null;
    }

    /** @return array{status: string, message: string} */
    private function missingAssignedSkuFailure(ProductStateInterface $product): array
    {
        return [
            'status' => ProductSynchronizationResultInterface::STATUS_ATTENTION,
            'message' => (string)__(
                'Ergonode product creation for Magento SKU "%1" returned no native SKU. Creation does not '
                . 'write the Magento SKU attribute. Verify the remote product independently and reconcile '
                . 'its native SKU binding before retrying.',
                $product->getSku()
            ),
        ];
    }

    /**
     * @param array<string, ProductStateInterface> $batchProducts
     */
    private function translateReferences(
        ProductStateInterface $product,
        array $batchProducts
    ): ProductStateInterface {
        $references = $this->referenceSkus($product);
        $translation = [];
        if ($references !== []) {
            $productSkus = $this->productResolver->getProductSkusBySkus($references);
            $identities = $this->identityRegistry->getIdentitiesByProductIds(array_keys($productSkus));
            foreach ($productSkus as $productId => $magentoSku) {
                $identity = $identities[$productId] ?? null;
                if ($identity !== null) {
                    $translation[$magentoSku] = $identity->getErgonodeSku();
                    continue;
                }
                $dependency = $batchProducts[$magentoSku] ?? null;
                if ($dependency === null
                    || $dependency->getIdentityMode() === ProductIdentityInterface::MODE_SHARED
                ) {
                    $translation[$magentoSku] = $magentoSku;
                }
            }
            foreach ($references as $reference) {
                if (!isset($translation[$reference])) {
                    throw new LocalizedException(__(
                        'Product "%1" is blocked because dependency "%2" has no Ergonode SKU mapping.',
                        $product->getSku(),
                        $reference
                    ));
                }
            }
        }

        $values = [];
        foreach ($product->getValues() as $value) {
            if ($value->getType() !== 'product_relation') {
                $values[] = $value;
                continue;
            }
            $translations = [];
            foreach ($value->getTranslations() as $language => $items) {
                $translations[$language] = array_map(
                    static fn (string $sku): string => $translation[$sku],
                    is_array($items) ? $items : [$items]
                );
            }
            $values[] = new ProductAttributeValue(
                $value->getAttributeCode(),
                $value->getType(),
                $translations,
                $value->getTwoWayRelation(),
                $value instanceof ProductAttributeValueClearIntentInterface
                    ? $value->getClearedLanguageCodes()
                    : []
            );
        }
        $relations = new ProductRelationState(
            $product->getRelations()->getBindingCodes(),
            array_map(
                static fn (string $sku): string => $translation[$sku],
                $product->getRelations()->getVariantSkus()
            ),
            array_combine(
                array_map(
                    static fn (string $sku): string => $translation[$sku],
                    array_keys($product->getRelations()->getGroupedChildren())
                ),
                array_values($product->getRelations()->getGroupedChildren())
            ) ?: []
        );

        return $this->remoteState($product, (string)$product->getErgonodeSku(), $values, $relations);
    }

    /** @return string[] */
    private function referenceSkus(ProductStateInterface $product): array
    {
        $references = [
            ...$product->getRelations()->getVariantSkus(),
            ...array_keys($product->getRelations()->getGroupedChildren()),
        ];
        foreach ($product->getValues() as $value) {
            if ($value->getType() !== 'product_relation') {
                continue;
            }
            foreach ($value->getTranslations() as $items) {
                $references = [...$references, ...(is_array($items) ? $items : [$items])];
            }
        }

        return array_values(array_unique($references));
    }

    private function decoratePreparedState(
        ProductStateInterface $sourceState,
        ProductStateInterface $preparedState
    ): ProductStateInterface {
        foreach ($this->stateDecorators as $decorator) {
            if (!$decorator instanceof ProductPreparedStateDecoratorInterface) {
                throw new LocalizedException(__(
                    'Prepared product state decorator must implement the public contract.'
                ));
            }
            $preparedState = $decorator->decorate($sourceState, $preparedState);
        }

        return $preparedState;
    }

    /** @param \Ergonode\ProductPublisher\Api\Data\ProductAttributeValueInterface[] $values */
    private function remoteState(
        ProductStateInterface $source,
        string $ergonodeSku,
        array $values,
        ProductRelationState $relations,
        ?bool $createdInCurrentSynchronization = null
    ): ProductStateInterface {
        $createdInCurrentSynchronization ??= $source instanceof ProductCreationContextInterface
            && $source->wasCreatedInCurrentSynchronization();

        return new RemoteIdentityProductState(
            $source,
            $ergonodeSku,
            $values !== [] ? $values : $source->getValues(),
            $relations->getBindingCodes() !== []
                || $relations->getVariantSkus() !== []
                || $relations->getGroupedChildren() !== []
                ? $relations
                : $source->getRelations(),
            $createdInCurrentSynchronization
        );
    }
}
