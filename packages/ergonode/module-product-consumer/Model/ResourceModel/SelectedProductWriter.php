<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\ResourceModel;

use Ergonode\ProductConsumer\Model\Port\SelectedProductWriterInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

class SelectedProductWriter implements SelectedProductWriterInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly StoreManagerInterface $stores
    ) {
    }

    public function write(int $productId, array $values, array $clear): void
    {
        /** @var Product $defaultProduct */
        $defaultProduct = $this->products->getById($productId, true, 0, true);
        $attributes = $defaultProduct->getAttributes();
        $changes = [];
        foreach (array_unique([...array_keys($values), ...array_keys($clear)]) as $code) {
            $attribute = $attributes[$code] ?? null;
            if ($attribute === null) {
                throw new LocalizedException(__('Attribute "%1" does not belong to the product attribute set.', $code));
            }
            foreach ($clear[$code] ?? [] as $storeId) {
                if ($storeId === 0 && $attribute->getIsRequired()) {
                    throw new LocalizedException(
                        __('Required attribute "%1" has no default value in Ergonode.', $code)
                    );
                }
                $changes[$storeId][$code] = $storeId === 0 ? null : false;
            }
            foreach ($values[$code] ?? [] as $storeId => $value) {
                $changes[$storeId][$code] = $value;
            }
            if ((int)$attribute->getIsGlobal() === 1) {
                foreach ($changes as $storeId => &$fields) {
                    if ($storeId !== 0) {
                        unset($fields[$code]);
                    }
                }
                unset($fields);
            }
        }
        $this->validateWebsiteValues($attributes, $changes);
        ksort($changes);
        foreach ($changes as $storeId => $fields) {
            if ($fields === []) {
                continue;
            }
            /** @var Product $product */
            $product = $this->products->getById($productId, true, $storeId, true);
            $product->setStoreId($storeId);
            $required = [];
            foreach ($fields as $code => $value) {
                $product->setData($code, $value);
                if ($storeId !== 0 && $value === false && $attributes[$code]->getIsRequired()) {
                    // Magento's admin AttributeFilter applies the same rule for Use Default.
                    $required[$code] = $attributes[$code]->getIsRequired();
                    $attributes[$code]->setIsRequired(false);
                }
            }
            try {
                $this->products->save($product);
            } finally {
                foreach ($required as $code => $isRequired) {
                    $attributes[$code]->setIsRequired($isRequired);
                }
            }
        }
    }

    /**
     * @param array<string, \Magento\Eav\Model\Entity\Attribute\AbstractAttribute> $attributes
     * @param array<int, array<string, mixed>> $changes
     */
    private function validateWebsiteValues(array $attributes, array $changes): void
    {
        $websiteValues = [];
        foreach ($changes as $storeId => $fields) {
            if ($storeId === 0 || $fields === []) {
                continue;
            }
            $websiteId = (int)$this->stores->getStore($storeId)->getWebsiteId();
            foreach ($fields as $code => $value) {
                if ((int)$attributes[$code]->getIsGlobal() !== 2) {
                    continue;
                }
                if (isset($websiteValues[$websiteId][$code]) && $websiteValues[$websiteId][$code] !== $value) {
                    throw new LocalizedException(__(
                        'Attribute "%1" has conflicting values in stores of website %2. Magento uses website scope.',
                        $code,
                        $websiteId
                    ));
                }
                $websiteValues[$websiteId][$code] = $value;
            }
        }
    }
}
