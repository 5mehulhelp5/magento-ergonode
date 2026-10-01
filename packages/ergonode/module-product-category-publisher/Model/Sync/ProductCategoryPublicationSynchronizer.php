<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryPublisher\Model\Sync;

use Ergonode\ProductCategoryPublisher\Api\Data\ProductCategoryStateInterface;
use Ergonode\ProductCategoryPublisher\Model\GraphQl\ProductCategoryMutationBuilder;
use Ergonode\ProductCategoryPublisher\Model\GraphQl\RemoteProductCategoryStateLoader;
use Ergonode\ProductPublisher\Api\Data\ProductStateInterface;
use Ergonode\ProductPublisher\Api\Data\ProductSynchronizationResultInterface;
use Ergonode\ProductPublisher\Model\Data\ProductSynchronizationResult;
use Ergonode\Publisher\Api\Data\MutationOperationInterface;
use Ergonode\Publisher\Api\Data\MutationResultInterface;
use Ergonode\Publisher\Api\MutationBatchPlannerInterface;
use Ergonode\Publisher\Api\MutationExecutorInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class ProductCategoryPublicationSynchronizer
{
    public function __construct(
        private readonly RemoteProductCategoryStateLoader $remoteStateLoader,
        private readonly ProductCategoryPublicationPolicy $policy,
        private readonly ProductCategoryMutationBuilder $mutations,
        private readonly MutationBatchPlannerInterface $batchPlanner,
        private readonly MutationExecutorInterface $executor,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param ProductStateInterface[] $states
     * @param ProductSynchronizationResultInterface[] $baseResults
     * @return ProductSynchronizationResultInterface[]
     */
    public function synchronize(array $states, array $baseResults): array
    {
        $statesBySku = [];
        $resultsBySku = [];
        foreach ($states as $state) {
            $statesBySku['sku:' . $state->getSku()] = $state;
        }
        foreach ($baseResults as $result) {
            $resultsBySku['sku:' . $result->getSku()] = $result;
        }
        $eligible = array_filter(
            $statesBySku,
            static fn (ProductStateInterface $state): bool => $state instanceof ProductCategoryStateInterface
                && !$state->isDeleted()
                && ($resultsBySku['sku:' . $state->getSku()] ?? null)?->isSuccessful()
        );
        if ($eligible === []) {
            return $baseResults;
        }
        try {
            $remoteStates = $this->remoteStateLoader->load(array_values(array_map(
                static fn (ProductStateInterface $state): string => $state->getSku(),
                $eligible
            )));
        } catch (Throwable $exception) {
            return $this->readFailure($baseResults, $eligible, $exception);
        }
        $operations = [];
        foreach ($eligible as $stateKey => $state) {
            if (!$state instanceof ProductCategoryStateInterface) {
                continue;
            }
            $sku = $state->getSku();
            $message = $this->policy->getBlockingMessage($state);
            if ($message !== null) {
                $base = $resultsBySku[$stateKey];
                $resultsBySku[$stateKey] = new ProductSynchronizationResult(
                    $sku,
                    ProductSynchronizationResultInterface::STATUS_FAILED,
                    $base->getResults(),
                    $message
                );
                continue;
            }
            $remoteKey = 'sku:' . $sku;
            $remoteCodes = array_key_exists($remoteKey, $remoteStates) ? $remoteStates[$remoteKey] : [];
            $change = $this->policy->plan($state, $remoteCodes ?? []);
            if ($change->getAddedCategoryCodes() !== []) {
                $operations[] = $this->mutations->add($sku, $change->getAddedCategoryCodes());
            }
            if ($remoteCodes !== null && $change->getRemovedCategoryCodes() !== []) {
                $operations[] = $this->mutations->remove($sku, $change->getRemovedCategoryCodes());
            }
        }
        foreach ($this->execute($operations) as $stateKey => $mutationResults) {
            $base = $resultsBySku[$stateKey];
            $failed = array_values(array_filter(
                $mutationResults,
                static fn (MutationResultInterface $result): bool =>
                    $result->getStatus() !== MutationResultInterface::STATUS_SUCCESS
            ));
            $resultsBySku[$stateKey] = new ProductSynchronizationResult(
                $base->getSku(),
                $failed === []
                    ? ProductSynchronizationResultInterface::STATUS_SUCCESS
                    : ProductSynchronizationResultInterface::STATUS_FAILED,
                [...$base->getResults(), ...$mutationResults],
                $failed === [] ? $base->getMessage() : $this->failureMessage($failed[0])
            );
        }

        return array_map(
            static fn (ProductSynchronizationResultInterface $result): ProductSynchronizationResultInterface =>
                $resultsBySku['sku:' . $result->getSku()] ?? $result,
            $baseResults
        );
    }

    /**
     * @param ProductSynchronizationResultInterface[] $baseResults
     * @param array<string, ProductStateInterface> $eligible
     * @return ProductSynchronizationResultInterface[]
     */
    private function readFailure(array $baseResults, array $eligible, Throwable $exception): array
    {
        $this->logger->error('Product category state read failed after base publication.', [
            'skus' => array_values(array_map(
                static fn (ProductStateInterface $state): string => $state->getSku(),
                $eligible
            )),
            'stage' => 'category_state_read',
            'exception_class' => $exception::class,
            'source_file' => $exception->getFile(),
            'source_line' => $exception->getLine(),
        ]);
        return array_map(
            static function (ProductSynchronizationResultInterface $base) use (
                $eligible
            ): ProductSynchronizationResultInterface {
                if (!isset($eligible['sku:' . $base->getSku()])) {
                    return $base;
                }
                return new ProductSynchronizationResult(
                    $base->getSku(),
                    ProductSynchronizationResultInterface::STATUS_FAILED,
                    $base->getResults(),
                    (string)__(
                        'Product "%1": the base publication stage completed, but its Ergonode categories '
                        . 'could not be read. Category assignments were not sent. Check the Magento log.',
                        $base->getSku()
                    )
                );
            },
            $baseResults
        );
    }

    /**
     * @param MutationOperationInterface[] $operations
     * @return array<string, MutationResultInterface[]>
     */
    private function execute(array $operations): array
    {
        $results = [];
        if ($operations === []) {
            return $results;
        }
        foreach ($this->batchPlanner->plan($operations) as $batch) {
            foreach ($this->executor->execute($batch)->getResults() as $result) {
                $sku = (string)($result->getOperation()->getMetadata()['entity_sku'] ?? '');
                $results['sku:' . $sku][] = $result;
            }
        }

        return $results;
    }

    private function failureMessage(MutationResultInterface $result): string
    {
        $errors = $result->getErrors();
        $message = is_array($errors[0] ?? null) ? trim((string)($errors[0]['message'] ?? '')) : '';

        return $message !== '' ? $message : 'Product category mutation failed.';
    }
}
