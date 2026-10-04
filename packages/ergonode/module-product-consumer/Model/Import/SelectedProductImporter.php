<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Import;

use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Ergonode\Product\Api\ProductIdentityServiceInterface;
use Ergonode\ProductConsumer\Model\GraphQl\RemoteProductLoader;
use Ergonode\ProductConsumer\Model\Magento\ProductAttributeValueMapper;
use Ergonode\ProductConsumer\Model\Magento\MappedMagentoSkuResolver;
use Ergonode\ProductConsumer\Model\Magento\MagentoSkuSynchronizer;
use Ergonode\ProductConsumer\Model\Magento\SelectedProductStateSynchronizerPool;
use Ergonode\ProductConsumer\Model\Port\SelectedProductWriterInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Eav\Api\AttributeManagementInterface;
use Magento\Framework\Exception\LocalizedException;

class SelectedProductImporter
{
    private const array EXCLUDED_ATTRIBUTES = [
        'sku', 'entity_id', 'row_id', 'type_id', 'attribute_set_id',
        'created_at', 'updated_at', 'media_gallery', 'tier_price',
    ];

    public function __construct(
        private readonly RemoteProductLoader $loader,
        private readonly ProductAttributeValueMapper $mapper,
        private readonly SelectedProductWriterInterface $writer,
        private readonly ProductRepositoryInterface $products,
        private readonly AttributeManagementInterface $attributes,
        private readonly MappedMagentoSkuResolver $skuResolver,
        private readonly MagentoSkuSynchronizer $skuSynchronizer,
        private readonly ProductIdentityServiceInterface $identities,
        private readonly SelectedProductStateSynchronizerPool $stateSynchronizers,
        private readonly ?\Ergonode\ProductConsumer\Model\Pipeline\BatchScope $batchScope = null
    ) {
    }

    public function import(ProductIdentityInterface $identity): void
    {
        $this->importSource($identity, $this->loader->load($identity->getErgonodeSku()));
    }

    public function importSource(ProductIdentityInterface $identity, ?\Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct $source): void
    {
        $productId = $identity->getProductId();
        $product = $this->products->getById($productId, true, 0, true);
        if ($source === null) {
            throw new LocalizedException(__('The mapped product does not exist in Ergonode.'));
        }
        $codes = [];
        foreach ($this->attributes->getAttributes(Product::ENTITY, (int)$product->getAttributeSetId()) as $attribute) {
            $codes[] = (string)$attribute->getAttributeCode();
        }
        $codes = array_values(array_diff($codes, self::EXCLUDED_ATTRIBUTES));
        $magentoSku = $identity->getIdentityMode() === ProductIdentityInterface::MODE_MAPPED
            ? (string)$product->getSku()
            : $this->skuResolver->resolve($source, $identity->getIdentityMode());
        $this->skuSynchronizer->validate($productId, $magentoSku, $source->sku);
        $this->skuSynchronizer->synchronizeIdentityAttribute($productId, $source->sku, $identity->getIdentityMode());
        $mapped = $this->mapper->map($source->attributes, $codes);
        $this->skuSynchronizer->assertIdentityAttributeNotMapped($mapped, $identity->getIdentityMode());
        $this->writer->write($productId, $mapped['values'], $mapped['clear']);
        $this->skuSynchronizer->synchronize($productId, $magentoSku, $source->sku);
        $this->stateSynchronizers->synchronize($productId, $magentoSku, $source, $codes);
        $record = fn() => $this->identities->recordImported(
            $productId,
            $source->sku,
            hash('sha256', 'selected-attributes-v1:' . $source->contentHash())
        );
        if (!($this->batchScope?->afterSuccess($record) ?? false)) { $record(); }
    }
}
