<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Magento;

use Ergonode\Product\Api\ProductIdentityServiceInterface;
use Ergonode\Product\Api\MagentoIdentityAttributeInterface;
use Ergonode\Product\Api\ProductIdentityModeProviderInterface;
use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class MagentoSkuSynchronizer
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductIdentityServiceInterface $identityService,
        private readonly MagentoIdentityAttributeInterface $identityAttribute,
        private readonly ProductIdentityModeProviderInterface $identityModeProvider
    ) {
    }

    public function synchronizeIdentityAttribute(int $productId, string $ergonodeSku, string $identityMode): void
    {
        if ($identityMode === ProductIdentityInterface::MODE_MAPPED) {
            $this->identityAttribute->write($productId, $ergonodeSku);
        }
    }

    /** @param array{values: array<string, mixed>, clear: array<string, mixed>} $mapped */
    public function assertIdentityAttributeNotMapped(array $mapped, string $identityMode): void
    {
        if ($identityMode !== ProductIdentityInterface::MODE_MAPPED) {
            return;
        }
        $code = $this->identityAttribute->getCode();
        if (array_key_exists($code, $mapped['values']) || array_key_exists($code, $mapped['clear'])) {
            throw new LocalizedException(__(
                'Magento attribute "%1" is reserved for native Ergonode SKU and cannot be an ordinary value mapping.',
                $code
            ));
        }
    }

    public function synchronize(int $productId, string $magentoSku, string $ergonodeSku): void
    {
        $this->validate($productId, $magentoSku, $ergonodeSku);
        $magentoSku = trim($magentoSku);
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('catalog_product_entity');
        $current = (string)$connection->fetchOne(
            $connection->select()->from($table, ['sku'])->where('entity_id = ?', $productId)->limit(1)
        );
        if ($current === $magentoSku) {
            return;
        }
        try {
            $product = $this->productRepository->getById($productId, true, 0, true);
            $product->setSku($magentoSku);
            $this->productRepository->save($product);
        } catch (Throwable $exception) {
            throw new LocalizedException(__('Unable to synchronize Magento SKU from Ergonode.'), $exception);
        }
    }

    public function validate(int $productId, string $magentoSku, string $ergonodeSku): void
    {
        $magentoSku = trim($magentoSku);
        if ($productId < 1 || $magentoSku === '') {
            throw new LocalizedException(__('Magento SKU received from Ergonode cannot be empty.'));
        }
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('catalog_product_entity');
        $ownerId = $connection->fetchOne(
            $connection->select()->from($table, ['entity_id'])->where('sku = ?', $magentoSku)->limit(1)
        );
        if ($ownerId !== false && (int)$ownerId !== $productId) {
            throw new LocalizedException(__('Magento SKU "%1" is already used by another product.', $magentoSku));
        }
        $identity = $this->identityService->getIdentitiesByProductIds([$productId])[$productId] ?? null;
        if ($identity !== null && $identity->getErgonodeSku() !== $ergonodeSku) {
            throw new LocalizedException(__(
                'Magento product ID %1 is already bound to conflicting Ergonode SKU "%2".',
                $productId,
                $identity->getErgonodeSku()
            ));
        }
        if ($identity?->getIdentityMode() === ProductIdentityInterface::MODE_MAPPED
            || $this->identityModeProvider->getMode() === ProductIdentityInterface::MODE_MAPPED
        ) {
            $value = trim((string)($this->identityAttribute->getValuesByProductIds([$productId])[$productId] ?? ''));
            $owner = $this->identityAttribute->findProductId($ergonodeSku);
            if (($value !== '' && $value !== $ergonodeSku)
                || ($owner !== null && $owner !== $productId)
            ) {
                throw new LocalizedException(__(
                    'Ergonode SKU "%1" conflicts with the configured Magento identity attribute. '
                        . 'Reconcile or migrate the historical binding before import.',
                    $ergonodeSku
                ));
            }
        }
    }
}
