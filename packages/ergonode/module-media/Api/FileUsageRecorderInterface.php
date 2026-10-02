<?php

declare(strict_types=1);

namespace Ergonode\Media\Api;

use Ergonode\Media\Model\ValueObject\File\FileUsageSet;

interface FileUsageRecorderInterface
{
    /**
     * @param int $productId
     * @param FileUsageSet $references
     * @return void
     */
    public function synchronize(int $productId, FileUsageSet $references): void;
}
