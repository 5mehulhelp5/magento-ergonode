<?php

declare(strict_types=1);

namespace Ergonode\Media\Api;

use Ergonode\Media\Model\ValueObject\Gallery\GallerySelection;

interface GallerySchedulerInterface
{
    /**
     * @param int $productId
     * @param GallerySelection|null $selection
     * @return void
     */
    public function schedule(int $productId, ?GallerySelection $selection): void;
}
