<?php

declare(strict_types=1);

namespace Ergonode\Media\Api;

interface GalleryAttributeOptionsInterface
{
    /**
     * List currently available Ergonode Gallery attributes by code.
     *
     * @return array<string, string>
     */
    public function getOptions(): array;
}
