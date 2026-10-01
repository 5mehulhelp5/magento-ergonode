<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Api;

use Magento\Framework\Exception\LocalizedException;

interface TemplateSnapshotRefresherInterface
{
    /**
     * Refresh the Ergonode template snapshot without changing Magento attribute sets or mappings.
     *
     * @return array{
     *     events: int,
     *     imported: int,
     *     changed: int,
     *     unchanged: int,
     *     cursor: string|null
     * }
     * @throws LocalizedException
     */
    public function refresh(): array;
}
