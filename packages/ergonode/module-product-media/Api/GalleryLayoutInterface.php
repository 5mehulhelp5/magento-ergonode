<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Api;

interface GalleryLayoutInterface
{
    /** @param list<string> $gallery
     * @param array<string,int> $positions Source path to requested position.
     * @return list<string>
     */
    public function arrange(array $gallery, array $positions): array;
}
