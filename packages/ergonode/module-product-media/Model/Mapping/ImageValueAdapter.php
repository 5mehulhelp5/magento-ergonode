<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Model\Mapping;

use Ergonode\ProductAttribute\Api\ValueAdapterInterface;
use Ergonode\ProductMedia\Api\ImageRolesInterface;
use Ergonode\ProductMedia\Api\GalleryConfigurationInterface;

class ImageValueAdapter implements ValueAdapterInterface
{
    public function __construct(
        private readonly ImageRolesInterface $roles,
        private readonly GalleryConfigurationInterface $config,
        private readonly bool $importEnabled = false
    ) {
    }
    public function supports(string $attributeCode): bool
    {
        return array_key_exists($attributeCode, $this->roles->getOptions());
    }
    public function isAvailable(string $direction): bool
    {
        return $direction === 'import' && $this->importEnabled && $this->config->isSynchronizationEnabled();
    }
}
