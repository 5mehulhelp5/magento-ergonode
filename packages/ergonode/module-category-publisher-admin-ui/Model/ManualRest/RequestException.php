<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Model\ManualRest;

use Magento\Framework\Exception\LocalizedException;

class RequestException extends LocalizedException
{
    public function __construct(private readonly int $statusCode)
    {
        parent::__construct(__('Ergonode REST request failed with HTTP status %1.', $statusCode));
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
