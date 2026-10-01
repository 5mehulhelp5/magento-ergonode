<?php

declare(strict_types=1);

namespace Ergonode\Product\Model\ResourceModel;

use Ergonode\Product\Api\MagentoIdentityAttributeInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

class MagentoIdentityAttribute implements MagentoIdentityAttributeInterface
{
    public const string XML_PATH_ATTRIBUTE = 'ergonode_products/identity/magento_attribute';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EavConfig $eavConfig,
        private readonly ResourceConnection $resourceConnection,
        private readonly ProductResource $productResource
    ) {
    }

    public function getCode(): string
    {
        return trim((string)$this->scopeConfig->getValue(self::XML_PATH_ATTRIBUTE));
    }

    public function getEligibleAttributes(): array
    {
        $options = [];
        foreach ($this->eavConfig->getEntityAttributes(Product::ENTITY) as $attribute) {
            if (!$attribute instanceof AbstractAttribute || !$this->isEligible($attribute)) {
                continue;
            }
            $code = (string)$attribute->getAttributeCode();
            $options[$code] = (string)$attribute->getFrontendLabel() ?: $code;
        }
        ksort($options);

        return $options;
    }

    public function validate(): void
    {
        $this->validateCode($this->getCode());
    }

    public function validateCode(string $code): void
    {
        $this->attribute($code);
    }

    public function getValuesByProductIds(array $productIds): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if ($productIds === []) {
            return [];
        }
        $attribute = $this->attribute();
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('catalog_product_entity');
        $select = $connection->select()->from(['product' => $table], ['entity_id'])
            ->where('product.entity_id IN (?)', $productIds);
        if ($attribute->getBackendType() === 'static') {
            $select->columns(['identity_value' => $this->getCode()], 'product');
        } else {
            $linkField = $this->productResource->getLinkField();
            $select->joinLeft(
                ['identity_value' => $this->resourceConnection->getTableName($attribute->getBackendTable())],
                'identity_value.' . $linkField . ' = product.' . $linkField
                    . ' AND identity_value.attribute_id = ' . (int)$attribute->getAttributeId()
                    . ' AND identity_value.store_id = 0',
                ['identity_value' => 'value']
            );
        }
        $values = [];
        foreach ($connection->fetchAll($select) as $row) {
            $values[(int)$row['entity_id']] = trim((string)($row['identity_value'] ?? ''));
        }

        return $values;
    }

    public function findProductId(string $ergonodeSku): ?int
    {
        $ergonodeSku = trim($ergonodeSku);
        if ($ergonodeSku === '') {
            return null;
        }
        return $this->findProductIds([$ergonodeSku])[$ergonodeSku] ?? null;
    }

    public function findProductIds(array $ergonodeSkus): array
    {
        $ergonodeSkus = array_values(array_unique(array_filter(array_map(
            static fn (mixed $sku): string => is_string($sku) ? trim($sku) : '',
            $ergonodeSkus
        ))));
        if ($ergonodeSkus === []) {
            return [];
        }
        $attribute = $this->attribute();
        $connection = $this->resourceConnection->getConnection();
        $productTable = $this->resourceConnection->getTableName('catalog_product_entity');
        $select = $connection->select()->from(['product' => $productTable], ['entity_id']);
        if ($attribute->getBackendType() === 'static') {
            $select->columns(['identity_value' => $this->getCode()], 'product')
                ->where('product.' . $this->getCode() . ' IN (?)', $ergonodeSkus);
        } else {
            $linkField = $this->productResource->getLinkField();
            $select->joinInner(
                ['identity_value' => $this->resourceConnection->getTableName($attribute->getBackendTable())],
                'identity_value.' . $linkField . ' = product.' . $linkField
                    . ' AND identity_value.attribute_id = ' . (int)$attribute->getAttributeId()
                    . ' AND identity_value.store_id = 0',
                ['identity_value' => 'value']
            )->where('identity_value.value IN (?)', $ergonodeSkus);
        }
        $owners = [];
        foreach ($connection->fetchAll($select) as $row) {
            $sku = trim((string)$row['identity_value']);
            if (isset($owners[$sku]) && $owners[$sku] !== (int)$row['entity_id']) {
                throw new LocalizedException(__(
                    'Ergonode SKU "%1" occurs in more than one Magento product identity attribute.',
                    $sku
                ));
            }
            $owners[$sku] = (int)$row['entity_id'];
        }
        return $owners;
    }

    public function write(int $productId, string $ergonodeSku): void
    {
        $ergonodeSku = trim($ergonodeSku);
        if ($productId < 1 || $ergonodeSku === '' || strlen($ergonodeSku) > 64) {
            throw new LocalizedException(__('Ergonode SKU must be a non-empty value of at most 64 bytes.'));
        }
        $attribute = $this->attribute();
        $current = $this->getValuesByProductIds([$productId])[$productId] ?? null;
        if ($current === null) {
            throw new LocalizedException(__('Magento product ID %1 does not exist.', $productId));
        }
        if ($current !== '' && $current !== $ergonodeSku) {
            throw new LocalizedException(__(
                'Magento product ID %1 has a different Ergonode SKU in attribute "%2".',
                $productId,
                $this->getCode()
            ));
        }
        if ($current === $ergonodeSku) {
            return;
        }
        $ownerId = $this->findProductId($ergonodeSku);
        if ($ownerId !== null && $ownerId !== $productId) {
            throw new LocalizedException(__('Ergonode SKU "%1" belongs to another Magento product.', $ergonodeSku));
        }
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('catalog_product_entity');
        if ($attribute->getBackendType() === 'static') {
            $connection->update($table, [$this->getCode() => $ergonodeSku], ['entity_id = ?' => $productId]);
            return;
        }
        $linkField = $this->productResource->getLinkField();
        $linkValue = $linkField === 'entity_id' ? $productId : $connection->fetchOne(
            $connection->select()->from($table, [$linkField])->where('entity_id = ?', $productId)
        );
        $connection->insertOnDuplicate(
            $this->resourceConnection->getTableName($attribute->getBackendTable()),
            [
                'attribute_id' => (int)$attribute->getAttributeId(),
                'store_id' => 0,
                $linkField => $linkValue,
                'value' => $ergonodeSku,
            ],
            ['value']
        );
    }

    private function attribute(?string $code = null): AbstractAttribute
    {
        $code = trim($code ?? $this->getCode());
        if ($code === '' || preg_match('/^[a-z][a-z0-9_]*$/', $code) !== 1) {
            throw new LocalizedException(__('Specify a Magento product attribute for Ergonode SKU.'));
        }
        $attribute = $this->eavConfig->getAttribute(Product::ENTITY, $code);
        if (!$attribute instanceof AbstractAttribute || !$this->isEligible($attribute)) {
            throw new LocalizedException(__(
                'Magento attribute "%1" must be a unique, global text product attribute.',
                $code
            ));
        }

        return $attribute;
    }

    private function isEligible(AbstractAttribute $attribute): bool
    {
        if ((int)$attribute->getAttributeId() < 1
            || !in_array((string)$attribute->getBackendType(), ['varchar', 'text', 'static'], true)
            || (string)$attribute->getFrontendInput() !== 'text'
            || (int)$attribute->getIsGlobal() !== ScopedAttributeInterface::SCOPE_GLOBAL
            || !(bool)$attribute->getIsUnique()
        ) {
            return false;
        }
        if ($attribute->getBackendType() !== 'static') {
            return true;
        }

        return isset($this->resourceConnection->getConnection()->describeTable(
            $this->resourceConnection->getTableName('catalog_product_entity')
        )[(string)$attribute->getAttributeCode()]);
    }
}
