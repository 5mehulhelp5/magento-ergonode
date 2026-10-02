<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Api;

interface GalleryRulesInterface
{
    /** @return array<string, int> Ergonode Image code to one-based gallery position. */
    public function getAdditionalImages(): array;
    /** @return array{attribute:string,position:int}|null */
    public function getAdditionalRole(): ?array;
}
