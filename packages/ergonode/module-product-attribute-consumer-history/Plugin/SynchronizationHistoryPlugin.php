<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumerHistory\Plugin;

use Ergonode\ProductAttributeConsumer\Api\AttributeSynchronizationProcessInterface;
use Ergonode\ProductAttributeHistory\Api\HistoryOperationCaptureInterface;

class SynchronizationHistoryPlugin
{
    public function __construct(private readonly HistoryOperationCaptureInterface $capture)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function aroundExecuteUntilComplete(
        AttributeSynchronizationProcessInterface $subject,
        callable $proceed,
        ?int $pageSize = null,
        int $maxBatches = 100
    ): array {
        unset($subject);
        return $this->capture->execute('synchronize', static fn (): array => $proceed($pageSize, $maxBatches));
    }
}
