<?php

declare(strict_types=1);

namespace Ergonode\Core\Api;

use Magento\Framework\Exception\LocalizedException;

interface DownloadSourcePolicyInterface
{
    /**
     * Authorize a download URL against the configured Ergonode scheme, host and port.
     * @param string $url
     * @return void
     * @throws LocalizedException
     */
    public function authorize(string $url): void;
}
