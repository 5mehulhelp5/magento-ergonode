<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Api;

interface GalleryConfigurationInterface
{
    /**
     * Check whether Ergonode media gallery synchronization is enabled.
     *
     * @return bool
     */
    public function isSynchronizationEnabled(): bool;
    /**
     * Return the globally configured Ergonode Gallery attribute code.
     *
     * @return string
     */
    public function getGalleryAttributeCode(): string;
}
