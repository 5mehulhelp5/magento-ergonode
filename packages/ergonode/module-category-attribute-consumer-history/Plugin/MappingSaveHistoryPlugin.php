<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumerHistory\Plugin;

use Ergonode\CategoryAttributeConsumer\Api\MappingSynchronizationInterface;
use Ergonode\CategoryAttributeHistory\Api\HistoryOperationCaptureInterface;

class MappingSaveHistoryPlugin
{
    public function __construct(private readonly HistoryOperationCaptureInterface $capture)
    {
    }

    /**
     * @param callable(callable): array<string, mixed> $proceed
     * @param callable(): array<string, mixed> $save
     * @return array<string, mixed>
     */
    public function aroundExecute(
        MappingSynchronizationInterface $subject,
        callable $proceed,
        callable $save
    ): array {
        unset($subject);
        return $this->capture->execute('save', static fn (): array => $proceed($save));
    }
}
