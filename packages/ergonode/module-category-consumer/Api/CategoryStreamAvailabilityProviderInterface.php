<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Api;

use Magento\Framework\Phrase;

interface CategoryStreamAvailabilityProviderInterface
{
    /**
     * Explain why a stream cannot run, without starting synchronization.
     *
     * @param bool $data Check category data instead of the structural stream.
     * @return Phrase|null Null when synchronization is available.
     */
    public function getBlockingReason(bool $data = false): ?Phrase;
}
