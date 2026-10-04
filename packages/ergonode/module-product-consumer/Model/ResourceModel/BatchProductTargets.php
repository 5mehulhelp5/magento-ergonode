<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\ResourceModel;

use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Ergonode\Product\Api\MagentoIdentityAttributeInterface;
use Ergonode\ProductConsumer\Model\Magento\MappedMagentoSkuResolver;
use Ergonode\ProductConsumer\Model\Pipeline\BatchContext;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Eav\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

class BatchProductTargets
{
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly MappedMagentoSkuResolver $skus,
        private readonly MagentoIdentityAttributeInterface $identityAttribute,
        private readonly Config $eav,
        private readonly ProductResource $product
    ) {
    }

    /** One product-existence query per batch, with mapping and native identity included. */
    public function get(BatchContext $context): array
    {
        $sourceSkus = []; $lookupSkus = []; $ids = [];
        $mode = $this->skus->getConfiguredMode();
        foreach ($context->entries as $entry) {
            if ($entry->error !== null) { continue; }
            $sourceSkus[] = $entry->sku();
            if ($entry->productId !== null) { $ids[] = $entry->productId; }
            try {
                $lookupSkus[$entry->sku()] = $entry->source !== null
                    ? $this->skus->resolve($entry->source, $mode) : $entry->sku();
            } catch (LocalizedException) {
                // An existing mapping may use a different identity mode. The regular resolver
                // validates a missing assigned SKU later, if there is no such mapping.
                $lookupSkus[$entry->sku()] = '';
            }
        }
        if ($sourceSkus === []) { return []; }
        $db = $this->resource->getConnection();
        $select = $db->select()->from(['p' => $this->resource->getTableName('catalog_product_entity')],
            ['entity_id', 'sku', 'type_id', 'attribute_set_id'])
            ->joinLeft(['m' => $this->resource->getTableName('ergonode_product_mapping')], 'm.product_id = p.entity_id',
                ['ergonode_sku', 'identity_mode']);
        $conditions = [$db->quoteInto('m.ergonode_sku IN (?)', $sourceSkus),
            $db->quoteInto('p.sku IN (?)', array_values($lookupSkus) ?: [''])];
        if ($ids !== []) { $conditions[] = $db->quoteInto('p.entity_id IN (?)', $ids); }
        if ($mode === ProductIdentityInterface::MODE_MAPPED) {
            $this->identityAttribute->validate();
            $code = $this->identityAttribute->getCode();
            $attribute = $this->eav->getAttribute(Product::ENTITY, $code);
            if ($attribute->getBackendType() === 'static') {
                $select->columns(['native_sku' => $code], 'p');
                $conditions[] = $db->quoteInto('p.' . $code . ' IN (?)', $sourceSkus);
            } else {
                $link = $this->product->getLinkField();
                $select->joinLeft(['i' => $this->resource->getTableName($attribute->getBackendTable())],
                    'i.' . $link . ' = p.' . $link . ' AND i.store_id = 0 AND i.attribute_id = ' . (int)$attribute->getAttributeId(),
                    ['native_sku' => 'value']);
                $conditions[] = $db->quoteInto('i.value IN (?)', $sourceSkus);
            }
        }
        $rows = $db->fetchAll($select->where('(' . implode(') OR (', $conditions) . ')'));
        $mapped = []; $native = []; $bySku = []; $byId = []; $duplicates = [];
        foreach ($rows as $row) {
            $byId[(int)$row['entity_id']] = $row;
            $bySku[(string)$row['sku']] = $row;
            if (!empty($row['ergonode_sku'])) { $mapped[(string)$row['ergonode_sku']] = $row; }
            if (!empty($row['native_sku'])) {
                $key = (string)$row['native_sku'];
                if (isset($native[$key]) && $native[$key]['entity_id'] !== $row['entity_id']) {
                    $duplicates[$key] = true;
                }
                $native[$key] = $row;
            }
        }
        $targets = [];
        foreach ($context->entries as $entry) {
            if ($entry->error !== null) { continue; }
            if ($entry->productId === null && !isset($mapped[$entry->sku()]) && isset($duplicates[$entry->sku()])) {
                $context->fail($entry, 'preprocess:identity', new LocalizedException(__(
                    'Ergonode SKU "%1" matches multiple Magento products.', $entry->sku()
                )));
                continue;
            }
            $row = $entry->productId !== null ? ($byId[$entry->productId] ?? null)
                : ($mapped[$entry->sku()] ?? ($mode === ProductIdentityInterface::MODE_MAPPED
                    ? ($native[$entry->sku()] ?? null) : ($bySku[$lookupSkus[$entry->sku()]] ?? null)));
            if ($row === null) { $targets[$entry->sku()] = null; continue; }
            $entry->productId = (int)$row['entity_id'];
            $targets[$entry->sku()] = ['product_id' => $entry->productId, 'sku' => (string)$row['sku'],
                'type_id' => (string)$row['type_id'], 'attribute_set_id' => (int)$row['attribute_set_id'],
                'identity_mode' => (string)($row['identity_mode'] ?? $mode),
                'bound' => $row['ergonode_sku'] !== null];
        }
        return $targets;
    }
}
