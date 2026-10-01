<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Model\Mapping;

use Ergonode\ProductAttribute\Api\ValueAdapterInterface;
use InvalidArgumentException;

class ValueAdapterRegistry
{
    /**
     * @param array<string, ValueAdapterInterface> $adapters Stable codes persisted with mappings.
     */
    public function __construct(private readonly array $adapters = [])
    {
    }

    public function getRequiredCode(string $attributeCode): ?string
    {
        foreach ($this->adapters as $code => $adapter) {
            if ($adapter->supports($attributeCode)) {
                return $code;
            }
        }

        return null;
    }

    public function isAvailable(?string $code, string $attributeCode, string $direction): bool
    {
        if (!in_array($direction, ['import', 'publish'], true)) {
            throw new InvalidArgumentException('Unknown value adapter direction: ' . $direction);
        }
        if ($code === null || $code === '') {
            return $this->getRequiredCode($attributeCode) === null;
        }
        $adapter = $this->adapters[$code] ?? null;

        return $adapter !== null && $adapter->supports($attributeCode) && $adapter->isAvailable($direction);
    }
}
