<?php

declare(strict_types=1);

namespace Ergonode\ProductMediaConsumer\Model\Media;

use Ergonode\ProductConsumer\Api\ProductImportHashProviderInterface;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;
use Ergonode\ProductMedia\Api\GalleryConfigurationInterface;
use Ergonode\ProductMedia\Api\GalleryRulesInterface;
use Ergonode\ProductMedia\Exception\InvalidMediaConfigurationException;
use Ergonode\ProductConsumer\Exception\NonRetryableImportException;

class MediaImportHashProvider implements ProductImportHashProviderInterface
{
    public function __construct(
        private readonly GalleryConfigurationInterface $configuration,
        private readonly GalleryRulesInterface $rules
    ) {
    }

    public function getHash(RemoteProduct $source): string
    {
        try {
            return $this->configurationHash();
        } catch (InvalidMediaConfigurationException $exception) {
            throw new NonRetryableImportException(__('%1', $exception->getMessage()), $exception);
        }
    }

    private function configurationHash(): string
    {
        // The version also schedules existing File -> text/textarea mappings for migration.
        $enabled = $this->configuration->isSynchronizationEnabled();
        $images = $enabled ? $this->rules->getAdditionalImages() : [];
        ksort($images);
        return hash('sha256', json_encode([
            'version' => 1,
            'enabled' => $enabled,
            'gallery' => $this->configuration->getGalleryAttributeCode(),
            'images' => $images,
            'role' => $enabled ? $this->rules->getAdditionalRole() : null,
            'unmanaged_images' => $enabled ? $this->configuration->getUnmanagedImagesMode()->value : null,
        ], JSON_THROW_ON_ERROR));
    }
}
