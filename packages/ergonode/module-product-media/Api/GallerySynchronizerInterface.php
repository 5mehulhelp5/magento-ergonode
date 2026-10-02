<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Api;

interface GallerySynchronizerInterface
{
    /**
     * Update gallery first and roles only after its successful update.
     * @param int $productId
     * @param list<array{path:string,position:int}> $desired
     * @param list<string> $managed
     * @param list<array{attribute:string,store_id:int,path:?string}> $mappedRoles
     * @return void
     */
    public function synchronize(int $productId, array $desired, array $managed, array $mappedRoles = []): void;
}
