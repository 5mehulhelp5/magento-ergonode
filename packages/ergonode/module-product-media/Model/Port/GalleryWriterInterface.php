<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Model\Port;

interface GalleryWriterInterface
{
    /** @param list<array{path:string,position:int}> $desired
     * @param list<string> $managed
     * @return list<string> Paths removed or hidden for this product; roles referencing them must be cleared.
     */
    public function synchronize(int $productId, array $desired, array $managed): array;
}
