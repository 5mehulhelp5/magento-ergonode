<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Queue;

use Ergonode\Product\Api\ProductIdentityServiceInterface;
use Ergonode\ProductConsumer\Model\Data\ProductImportWorkItem;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;
use Ergonode\ProductConsumer\Model\GraphQl\RemoteProductLoader;
use Ergonode\ProductConsumer\Model\Magento\ProductDeletionPolicy;
use Ergonode\ProductConsumer\Model\Magento\ProductAttributeValueMapper;
use Ergonode\ProductConsumer\Model\Magento\MagentoSkuSynchronizer;
use Ergonode\ProductConsumer\Model\Magento\ProductStateWriter;
use Ergonode\ProductConsumer\Model\Magento\ProductTargetPreparer;

class ProductImportProcessor
{
    private const string IMPORT_SCHEMA_VERSION = 'product-import-v2';
    public function __construct(
        private readonly RemoteProductLoader $remoteProductLoader,
        private readonly ProductDeletionPolicy $deletionPolicy,
        private readonly ProductTargetPreparer $targetPreparer,
        private readonly ProductStateWriter $stateWriter,
        private readonly ProductIdentityServiceInterface $identityService,
        private readonly ProductAttributeValueMapper $attributeValueMapper,
        private readonly MagentoSkuSynchronizer $magentoSkuSynchronizer,
        private readonly ProductImportHashProviderPool $hashProviders,
        private readonly ?\Ergonode\ProductConsumer\Model\Pipeline\BatchScope $batchScope = null
    ) {
    }

    public function process(ProductImportWorkItem $item): bool
    {
        $source = $this->remoteProductLoader->loadCurrent($item->sku,
            $item->operation === ProductImportWorkItem::OPERATION_DELETE ? null : $item->payload);
        return $this->processSource($item, $source);
    }

    public function processSource(ProductImportWorkItem $item, ?RemoteProduct $source): bool
    {
        return $source === null ? $this->deletionPolicy->deactivate($item->sku) : $this->synchronize($source);
    }

    private function synchronize(RemoteProduct $source): bool
    {
        $specialAttributes = $this->attributeValueMapper->mapSpecial($source->attributes);
        $prepared = $this->targetPreparer->prepare($source, $specialAttributes['values']);
        $productId = $prepared->productId;
        $this->batchScope?->recordProduct($productId);
        $this->magentoSkuSynchronizer->synchronizeIdentityAttribute(
            $productId,
            $source->sku,
            $prepared->identityMode
        );
        $hash = hash(
            'sha256',
            self::IMPORT_SCHEMA_VERSION
            . ':' . $source->contentHash()
            . ':' . json_encode($specialAttributes, JSON_THROW_ON_ERROR)
            . ':' . json_encode($this->hashProviders->getHashes($source), JSON_THROW_ON_ERROR)
        );
        if ($this->identityService->getImportHash($productId) === $hash) {
            $this->stateWriter->synchronizeUnchanged($productId, $prepared->magentoSku, $source);
            if ($prepared->currentMagentoSku !== $prepared->magentoSku) {
                $this->magentoSkuSynchronizer->synchronize($productId, $prepared->magentoSku, $source->sku);

                return true;
            }
            return false;
        }

        $this->stateWriter->write(
            $productId,
            $source,
            $prepared->typeAdapter,
            $prepared->currentMagentoSku,
            $specialAttributes,
            $prepared->identityMode
        );
        $this->magentoSkuSynchronizer->synchronize($productId, $prepared->magentoSku, $source->sku);
        $record = fn() => $this->identityService->recordImported($productId, $source->sku, $hash);
        if (!($this->batchScope?->afterSuccess($record) ?? false)) { $record(); }

        return true;
    }
}
