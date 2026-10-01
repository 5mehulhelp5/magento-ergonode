<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryAttribute\Model;

use Ergonode\ProductAttribute\Api\ValueAdapterInterface;
use Ergonode\ProductCategoryAttribute\Api\CategoryReferenceAttributeConfigInterface;

class CategoryReferenceValueAdapter implements ValueAdapterInterface
{
    public function __construct(
        private readonly CategoryReferenceAttributeConfigInterface $config,
        private readonly bool $importAvailable = false,
        private readonly bool $publishAvailable = false
    ) {
    }

    public function supports(string $attributeCode): bool
    {
        return $attributeCode !== '' && $this->config->isConfigured($attributeCode);
    }

    public function isAvailable(string $direction): bool
    {
        return match ($direction) {
            'import' => $this->importAvailable,
            'publish' => $this->publishAvailable,
            default => false,
        };
    }
}
