<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Model\ManualRest;

use Ergonode\Core\Api\Exception\RetryAfterExceptionInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

class RetryableRequestException extends LocalizedException implements RetryAfterExceptionInterface
{
    public function __construct(string $message, private readonly int $retryAfterSeconds)
    {
        parent::__construct(new Phrase($message));
    }

    public function getRetryAfterSeconds(): int
    {
        return max(1, $this->retryAfterSeconds);
    }
}
