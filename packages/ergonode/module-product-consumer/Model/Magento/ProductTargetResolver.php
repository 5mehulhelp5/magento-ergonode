<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Magento;

use Ergonode\Product\Api\ProductIdentityServiceInterface;
use Ergonode\Product\Api\MagentoIdentityAttributeInterface;
use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

class ProductTargetResolver
{
    private ?array $batchTargets = null;

    public function setBatchTargets(?array $targets): void
    {
        $this->batchTargets = $targets;
    }

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ProductIdentityServiceInterface $identityService,
        private readonly MagentoIdentityAttributeInterface $identityAttribute
    ) {
    }

    /** @return array{product_id: int, sku: string, type_id: string, attribute_set_id: int, identity_mode: string}|null */
    public function resolve(
        string $ergonodeSku,
        ?string $mappedMagentoSku = null,
        string $identityMode = ProductIdentityInterface::MODE_SHARED
    ): ?array {
        $ergonodeSku = trim($ergonodeSku);
        if ($this->batchTargets !== null && array_key_exists($ergonodeSku, $this->batchTargets)) {
            $target = $this->batchTargets[$ergonodeSku];
            if ($target !== null) {
                if (!$target['bound']) {
                    $this->identityService->bind($target['product_id'], $ergonodeSku, $identityMode);
                    $this->batchTargets[$ergonodeSku]['bound'] = true;
                }
                unset($target['bound']);
            }
            return $target;
        }
        $mapped = $this->identityService->getIdentitiesByErgonodeSkus([$ergonodeSku]);
        $identity = $mapped[0] ?? null;
        $mappedId = $identity?->getProductId();
        if ($mappedId === null && $identityMode === ProductIdentityInterface::MODE_MAPPED) {
            $mappedId = $this->identityAttribute->findProductId($ergonodeSku);
        }
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('catalog_product_entity');
        $lookupSku = match ($identityMode) {
            ProductIdentityInterface::MODE_ASSIGNED => trim((string)$mappedMagentoSku),
            ProductIdentityInterface::MODE_MAPPED => '',
            default => $ergonodeSku,
        };
        $bySku = $mappedId === null && $lookupSku !== '' ? $connection->fetchRow(
            $connection->select()
                ->from($table, ['entity_id', 'sku', 'type_id', 'attribute_set_id'])
                ->where('sku = ?', $lookupSku)
                ->limit(1)
        ) : false;
        $byMapping = $mappedId !== null ? $connection->fetchRow(
            $connection->select()
                ->from($table, ['entity_id', 'sku', 'type_id', 'attribute_set_id'])
                ->where('entity_id = ?', $mappedId)
                ->limit(1)
        ) : false;
        $row = $byMapping !== false ? $byMapping : $bySku;
        if ($row === false) {
            return null;
        }

        if ($identity === null) {
            $this->identityService->bind((int)$row['entity_id'], $ergonodeSku, $identityMode);
        }

        return [
            'product_id' => (int)$row['entity_id'],
            'sku' => (string)$row['sku'],
            'type_id' => (string)$row['type_id'],
            'attribute_set_id' => (int)$row['attribute_set_id'],
            'identity_mode' => $identity?->getIdentityMode() ?? $identityMode,
        ];
    }
}
