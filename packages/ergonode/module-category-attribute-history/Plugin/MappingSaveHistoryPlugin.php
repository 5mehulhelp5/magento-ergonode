<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeHistory\Plugin;

use Ergonode\CategoryAttribute\Api\AttributeMappingWriterInterface;
use Ergonode\CategoryAttributeHistory\Api\HistoryOperationCaptureInterface;

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
        AttributeMappingWriterInterface $subject,
        callable $proceed,
        array $mappings,
        array $visibility
    ): array {
        unset($subject);
        return $this->capture->execute('save', static fn (): array => $proceed($mappings, $visibility));
    }
}
