<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Api;

interface ImageRulesNormalizerInterface
{
    /** @param array<string|int,array{attribute?:string,position?:string|int}> $rows
     * @return list<array{attribute:string,position:int}>
     */
    public function normalize(array $rows): array;
}
