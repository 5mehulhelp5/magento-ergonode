<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Type;

use Ergonode\ProductConsumer\Api\ProductTypeAdapterInterface;
use Ergonode\ProductConsumer\Api\ProductTypeAdapterPoolInterface;
use Magento\Framework\Exception\LocalizedException;

class ProductTypeAdapterPool implements ProductTypeAdapterPoolInterface
{
    /** @var array<string, ProductTypeAdapterInterface> */
    private array $byMagentoType = [];

    /** @param ProductTypeAdapterInterface[] $adapters */
    public function __construct(array $adapters = [])
    {
        foreach ($adapters as $adapter) {
            $type = trim($adapter->getMagentoType());
            if ($type === '' || isset($this->byMagentoType[$type])) {
                throw new LocalizedException(__('Duplicate or empty Magento product consumer type "%1".', $type));
            }
            $this->byMagentoType[$type] = $adapter;
        }
    }

    public function resolve(
        string $ergonodeType,
        ?string $existingMagentoType = null,
        ?string $requestedMagentoType = null
    ): ProductTypeAdapterInterface {
        $ergonodeType = trim($ergonodeType);
        $existingMagentoType = $this->normalizeNullable($existingMagentoType);
        $requestedMagentoType = $this->normalizeNullable($requestedMagentoType);

        if ($requestedMagentoType !== null) {
            return $this->requireCompatible($requestedMagentoType, $ergonodeType);
        }
        if ($existingMagentoType !== null) {
            return $this->requireCompatible($existingMagentoType, $ergonodeType);
        }

        $defaults = array_values(array_filter(
            $this->byMagentoType,
            static fn (ProductTypeAdapterInterface $adapter): bool =>
                $adapter->getErgonodeType() === $ergonodeType && $adapter->isDefaultForErgonodeType()
        ));
        if (count($defaults) !== 1) {
            throw new LocalizedException(__(
                'Ergonode product type "%1" has no unambiguous default Magento consumer adapter.',
                $ergonodeType
            ));
        }

        return $defaults[0];
    }

    private function requireCompatible(string $magentoType, string $ergonodeType): ProductTypeAdapterInterface
    {
        $adapter = $this->byMagentoType[$magentoType] ?? null;
        if ($adapter === null || $adapter->getErgonodeType() !== $ergonodeType) {
            throw new LocalizedException(__(
                'Magento product type "%1" cannot consume Ergonode product type "%2".',
                $magentoType,
                $ergonodeType
            ));
        }

        return $adapter;
    }

    private function normalizeNullable(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : '';

        return $value !== '' ? $value : null;
    }
}
