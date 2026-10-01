<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumerHistory\Plugin;

use Ergonode\ProductAttributeConsumer\Api\AttributeAutoMapperInterface;
use Ergonode\ProductAttributeHistory\Api\HistoryOperationCaptureInterface;

class AutoMappingHistoryPlugin
{
    public function __construct(private readonly HistoryOperationCaptureInterface $capture)
    {
    }

    /**
     * @param callable(): array<string, mixed> $proceed
     * @return array<string, mixed>
     */
    public function aroundSynchronize(
        AttributeAutoMapperInterface $subject,
        callable $proceed
    ): array {
        unset($subject);
        return $this->capture->execute('auto_map', static fn (): array => $proceed());
    }
}
