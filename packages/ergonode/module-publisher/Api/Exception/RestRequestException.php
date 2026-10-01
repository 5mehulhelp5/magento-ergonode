<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Api\Exception;

use Ergonode\Core\Api\Exception\RetryAfterExceptionInterface;
use Magento\Framework\Exception\LocalizedException;

class RestRequestException extends LocalizedException implements RetryAfterExceptionInterface
{
    public function __construct(private readonly int $status, private readonly ?int $retryAfter = null)
    {
        parent::__construct(match ($status) {
            400 => __(
                'Ergonode rejected the request parameters (HTTP 400). '
                . 'Check the integration request format. Details were recorded in the Magento log.'
            ),
            405 => __(
                'Ergonode rejected the request method (HTTP 405). '
                . 'The integration request must be corrected. Details were recorded in the Magento log.'
            ),
            default => __('Ergonode REST request failed (HTTP %1).', $status),
        });
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    public function getRetryAfterSeconds(): ?int
    {
        return $this->retryAfter;
    }
}
