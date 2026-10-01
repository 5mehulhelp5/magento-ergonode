<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumerHistory\Plugin;

use Ergonode\ProductAttributeConsumer\Model\Mapping\AttributeMappingSaver;
use Ergonode\ProductAttributeHistory\Api\HistoryOperationCaptureInterface;

class MappingSaveHistoryPlugin
{
    public function __construct(private readonly HistoryOperationCaptureInterface $capture)
    {
    }

    /**
     * @param  array<int, array<string, mixed>> $mappings
     * @param  array<int, array<string, mixed>> $visibility
     * @return array<string, mixed>
     */
    public function aroundSave(
        AttributeMappingSaver $subject,
        callable $proceed,
        array $mappings,
        array $visibility
    ): array {
        unset($subject);
        return $this->capture->execute('save', static fn (): array => $proceed($mappings, $visibility));
    }

    /**
     * @param  array<int, array<string, mixed>> $mappings
     * @return array<string, mixed>
     */
    public function aroundSaveAdditions(
        AttributeMappingSaver $subject,
        callable $proceed,
        array $mappings
    ): array {
        unset($subject);
        return $this->capture->execute('auto_map', static fn (): array => $proceed($mappings));
    }
}
