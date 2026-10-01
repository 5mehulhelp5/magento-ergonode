<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeHistory\Plugin;

use Ergonode\ProductAttribute\Model\Mapping\OptionMappingSaver;
use Ergonode\ProductAttributeHistory\Api\HistoryOperationCaptureInterface;

class OptionMappingSaveHistoryPlugin
{
    public function __construct(private readonly HistoryOperationCaptureInterface $capture)
    {
    }

    /**
     * @param array<int, array<string, mixed>> $mappings
     * @param array<int, array<string, mixed>> $visibility
     * @return array<string, mixed>
     */
    public function aroundSave(
        OptionMappingSaver $subject,
        callable $proceed,
        int $attributeMappingId,
        array $mappings,
        array $visibility
    ): array {
        unset($subject);
        return $this->capture->execute(
            'save_options',
            static fn (): array => $proceed($attributeMappingId, $mappings, $visibility)
        );
    }
}
