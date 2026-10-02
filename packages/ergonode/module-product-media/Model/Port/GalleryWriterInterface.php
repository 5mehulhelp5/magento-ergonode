<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Model\Port;

interface GalleryWriterInterface
{
    /** @param list<array{path:string,position:int}> $desired
     * @param list<string> $managed
     */
    public function synchronize(int $productId, array $desired, array $managed): void;
}
