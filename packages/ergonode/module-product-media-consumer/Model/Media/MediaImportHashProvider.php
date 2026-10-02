<?php

declare(strict_types=1);

namespace Ergonode\ProductMediaConsumer\Model\Media;

use Ergonode\ProductConsumer\Api\ProductImportHashProviderInterface;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;
use Ergonode\ProductMedia\Api\GalleryConfigurationInterface;
use Ergonode\ProductMedia\Api\GalleryRulesInterface;

class MediaImportHashProvider implements ProductImportHashProviderInterface
{
    public function __construct(
        private readonly GalleryConfigurationInterface $configuration,
        private readonly GalleryRulesInterface $rules
    ) {
    }

    public function getHash(RemoteProduct $source): string
    {
        // The version also schedules existing File -> text/textarea mappings for migration.
        $images = $this->rules->getAdditionalImages();
        ksort($images);
        return hash('sha256', json_encode([
            'version' => 1,
            'enabled' => $this->configuration->isSynchronizationEnabled(),
            'gallery' => $this->configuration->getGalleryAttributeCode(),
            'images' => $images,
            'role' => $this->rules->getAdditionalRole(),
        ], JSON_THROW_ON_ERROR));
    }
}
