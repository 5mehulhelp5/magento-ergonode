<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumerHistory\Plugin;

use Ergonode\ProductAttributeConsumer\Api\AttributeSynchronizationBatchInterface;
use Ergonode\ProductAttributeHistory\Api\HistoryOperationCaptureInterface;

class BatchHistoryPlugin
{
    public function __construct(private readonly HistoryOperationCaptureInterface $capture)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function aroundExecuteAutomatic(
        AttributeSynchronizationBatchInterface $subject,
        callable $proceed,
        ?string $cursor = null,
        ?int $pageSize = null,
        bool $refreshDefinitions = true
    ): array {
        unset($subject);
        return $this->capture->execute(
            'synchronize',
            static fn (): array => $proceed($cursor, $pageSize, $refreshDefinitions)
        );
    }
}
