<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Import;

use Ergonode\Product\Api\ProductIdentityServiceInterface;
use Ergonode\ProductAttributeConsumer\Api\ProductAttributeSourcePreparationInterface;
use Ergonode\ProductConsumer\Api\ProductImportBatchInterface;
use Ergonode\ProductConsumer\Api\ProductImportReadinessInterface;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Throwable;

class ProductImportBatch implements ProductImportBatchInterface
{
    public function __construct(
        private readonly ProductAttributeSourcePreparationInterface $attributePreparation,
        private readonly ProductImportReadinessInterface $readiness,
        private readonly ProductIdentityServiceInterface $identities,
        private readonly SelectedProductImporter $importer,
        private readonly LoggerInterface $logger
    ) {
    }

    public function import(array $productIds): array
    {
        if (!array_is_list($productIds) || $productIds === [] || count($productIds) > 50) {
            throw new LocalizedException(__('A batch must contain between 1 and 50 products.'));
        }
        foreach ($productIds as $id) {
            if (!is_int($id) || $id < 1) {
                throw new LocalizedException(__('Invalid product identifiers.'));
            }
        }
        if (count(array_unique($productIds)) !== count($productIds)) {
            throw new LocalizedException(__('Product identifiers must be unique.'));
        }
        $this->attributePreparation->prepare();
        $status = $this->readiness->getStatus();
        if (!$status['ready']) {
            throw new LocalizedException(__($status['message']));
        }
        $identities = $this->identities->getIdentitiesByProductIds($productIds);
        $results = [];
        foreach ($productIds as $id) {
            $identity = $identities[$id] ?? null;
            $result = ['product_id' => $id, 'code' => $identity?->getMagentoSku() ?? (string)$id,
                'status' => 'success', 'message' => (string)__('Mapped product attributes were updated in Magento.')];
            try {
                if ($identity === null) {
                    throw new LocalizedException(__('This product has no Ergonode SKU mapping.'));
                }
                $this->importer->import($identity);
            } catch (Throwable $exception) {
                $reference = substr(hash('sha256', uniqid((string)$id, true)), 0, 12);
                $this->logger->error('Ergonode product import failed.', [
                    'product_id' => $id, 'ergonode_sku' => $identity?->getErgonodeSku(),
                    'reference' => $reference, 'exception' => $exception,
                ]);
                $result['status'] = 'failed';
                $reason = $exception instanceof LocalizedException ? $exception->getMessage()
                    : (string)__('Unexpected error while importing the product.');
                $result['message'] = (string)__(
                    '%1 Product ID: %2. Ergonode SKU: %3. Earlier writes may have completed. Log reference: %4.',
                    $reason,
                    $id,
                    $identity?->getErgonodeSku() ?? '-',
                    $reference
                );
            }
            $results[] = $result;
        }

        return $results;
    }
}
