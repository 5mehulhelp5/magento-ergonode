<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\Sync;

use Ergonode\Core\Api\Exception\GraphQlRequestException;
use Ergonode\ProductPublisher\Api\Data\ProductStateInterface;
use Ergonode\ProductPublisher\Api\Data\ProductSynchronizationResultInterface;
use Ergonode\ProductPublisher\Api\ProductBatchSynchronizerInterface;
use Ergonode\ProductPublisher\Api\ProductPublicationSynchronizerInterface;
use Ergonode\ProductPublisher\Api\ProductSynchronizerInterface;
use Ergonode\ProductPublisher\Model\Data\ProductSynchronizationResult;
use Ergonode\ProductPublisher\Model\Source\ProductSourceLoader;
use Magento\Framework\Exception\LocalizedException;

class ProductPublicationSynchronizer implements ProductPublicationSynchronizerInterface
{
    public function __construct(
        private readonly ProductSourceLoader $sourceLoader,
        private readonly ProductDependencyLayerPlanner $layerPlanner,
        private readonly ProductIdentityCoordinator $identityCoordinator,
        private readonly ProductBatchSynchronizerInterface $productSynchronizer,
        private readonly ProductPublicationIdentityRecorder $identityRecorder
    ) {
    }

    public function synchronize(
        array $skus,
        string $mode = ProductSynchronizerInterface::MODE_UPDATE
    ): array {
        $selectedSkus = $this->normalizeSkus($skus);
        if ($selectedSkus === []) {
            return [];
        }

        $source = $this->sourceLoader->load($selectedSkus);
        $products = $this->indexProducts($source->getStates());
        $results = [];
        foreach ($source->getSkippedProductMessages() as $sku => $message) {
            $results[$sku] = $this->failure((string)$sku, $message);
            unset($products[$sku]);
        }
        foreach ($source->getSkippedProductWarnings() as $sku => $message) {
            $results[$sku] = $this->localWarning((string)$sku, $message);
            unset($products[$sku]);
        }
        foreach ($selectedSkus as $sku) {
            if (!isset($products[$sku]) && !isset($results[$sku])) {
                $results[$sku] = $this->localWarning(
                    $sku,
                    'The publication source returned no state or skip reason for the selected Magento SKU.'
                );
            }
        }

        $references = array_fill_keys(array_keys($results), false);
        foreach ($this->layerPlanner->plan($products) as $layer) {
            $ready = [];
            foreach ($layer as $sku) {
                $blockers = $this->dependencyBlockers($products[$sku], $references);
                if ($blockers !== []) {
                    $results[$sku] = new ProductSynchronizationResult(
                        $sku,
                        ProductSynchronizationResultInterface::STATUS_BLOCKED_REFERENCE,
                        message: implode(' ', $blockers)
                    );
                    $references[$sku] = false;
                    continue;
                }
                $ready[$sku] = $products[$sku];
            }

            foreach ($this->synchronizeLayer($ready, $mode) as $sku => $result) {
                if ($result->isSuccessful() && isset($source->getProductWarnings()[$sku])) {
                    $result = new ProductSynchronizationResult(
                        $result->getSku(),
                        $result->getStatus(),
                        $result->getResults(),
                        $result->getMessage(),
                        $source->getProductWarnings()[$sku]
                    );
                }
                $results[$sku] = $result;
                $references[$sku] = $result->isSuccessful();
            }
        }

        ksort($results);
        $this->identityRecorder->record(array_values($results), $products);

        return $results;
    }

    /**
     * @param array<string, ProductStateInterface> $products
     * @return array<string, ProductSynchronizationResultInterface>
     */
    private function synchronizeLayer(array $products, string $mode): array
    {
        if ($products === []) {
            return [];
        }
        $prepared = $this->identityCoordinator->prepare($products);
        $results = [];
        foreach ($prepared->getFailures() as $sku => $failure) {
            $results[$sku] = new ProductSynchronizationResult(
                (string)$sku,
                $failure['status'],
                message: $failure['message']
            );
        }
        $remoteStates = $prepared->getStates();
        if ($remoteStates === []) {
            return $results;
        }

        try {
            $remoteResults = $this->productSynchronizer->synchronizeBatch(array_values($remoteStates), $mode);
        } catch (GraphQlRequestException $exception) {
            throw $exception;
        } catch (LocalizedException $exception) {
            foreach (array_keys($remoteStates) as $sku) {
                $results[$sku] = $this->failure((string)$sku, $exception->getMessage());
            }

            return $results;
        }

        $resultsByRemoteSku = [];
        foreach ($remoteResults as $result) {
            $resultsByRemoteSku['sku:' . $result->getSku()] = $result;
        }
        foreach ($remoteStates as $magentoSku => $state) {
            // PHP converts integer-like Magento SKU keys to integers.
            $magentoSku = (string)$magentoSku;
            $remoteResult = $resultsByRemoteSku['sku:' . $state->getSku()] ?? null;
            if (!$remoteResult instanceof ProductSynchronizationResultInterface) {
                $results[$magentoSku] = $this->failure(
                    $magentoSku,
                    'Product synchronizer returned no correlated result.'
                );
                continue;
            }
            $results[$magentoSku] = new ProductSynchronizationResult(
                $magentoSku,
                $remoteResult->getStatus(),
                $remoteResult->getResults(),
                $remoteResult->getMessage()
            );
        }

        return $results;
    }

    /**
     * @param array<string, bool> $references
     * @return string[]
     */
    private function dependencyBlockers(
        ProductStateInterface $product,
        array $references
    ): array {
        $blockers = [];
        foreach ($this->relatedSkus($product) as $dependencySku) {
            if (array_key_exists($dependencySku, $references) && !$references[$dependencySku]) {
                $blockers[] = sprintf('Product prerequisite "%s" did not synchronize.', $dependencySku);
            }
        }

        return array_values(array_unique($blockers));
    }

    /** @return string[] */
    private function relatedSkus(ProductStateInterface $product): array
    {
        $skus = [
            ...$product->getRelations()->getVariantSkus(),
            ...array_keys($product->getRelations()->getGroupedChildren()),
        ];
        foreach ($product->getValues() as $value) {
            if ($value->getType() !== 'product_relation') {
                continue;
            }
            foreach ($value->getTranslations() as $translation) {
                $skus = [...$skus, ...(is_array($translation) ? $translation : [$translation])];
            }
        }

        return array_values(array_unique($skus));
    }

    /** @param ProductStateInterface[] $states @return array<string, ProductStateInterface> */
    private function indexProducts(array $states): array
    {
        $products = [];
        foreach ($states as $state) {
            if (isset($products[$state->getSku()])) {
                throw new LocalizedException(__('Magento source contains duplicate product "%1".', $state->getSku()));
            }
            $products[$state->getSku()] = $state;
        }

        return $products;
    }

    /** @param string[] $skus @return string[] */
    private function normalizeSkus(array $skus): array
    {
        $normalized = array_values(array_unique(array_filter(array_map(
            static fn (mixed $sku): string => is_string($sku) ? trim($sku) : '',
            $skus
        ))));
        sort($normalized);

        return $normalized;
    }

    private function failure(string $sku, string $message): ProductSynchronizationResultInterface
    {
        return new ProductSynchronizationResult(
            $sku,
            ProductSynchronizationResultInterface::STATUS_FAILED,
            message: $message
        );
    }

    private function localWarning(string $sku, string $message): ProductSynchronizationResultInterface
    {
        return new ProductSynchronizationResult(
            $sku,
            ProductSynchronizationResultInterface::STATUS_LOCAL_WARNING,
            message: $message
        );
    }
}
