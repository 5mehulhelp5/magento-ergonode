<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumerAdminUi\Plugin;

use Ergonode\ProductAttributeAdminUi\Model\Mapping\OptionMappingSaver;
use Ergonode\ProductAttributeConsumer\Model\Mapping\OptionMappingSaver as InboundMappingSaver;

class OptionMappingSaverPlugin
{
    public function __construct(private readonly InboundMappingSaver $mappingSaver)
    {
    }

    /**
     * @param  callable                         $proceed
     * @param  array<int, array<string, mixed>> $mappings
     * @param  array<int, array<string, mixed>> $visibility
     * @return array<string, int>
     */
    public function aroundSave(
        OptionMappingSaver $subject,
        callable $proceed,
        int $attributeMappingId,
        array $mappings,
        array $visibility
    ): array {
        foreach ($mappings as $mapping) {
            $right = $mapping['right'] ?? null;
            if (is_array($right) && (!empty($right['pending_create'])
                || str_starts_with((string)($right['code'] ?? ''), 'pending_'))
            ) {
                return $this->mappingSaver->save($attributeMappingId, $mappings, $visibility);
            }
        }

        return $proceed($attributeMappingId, $mappings, $visibility);
    }
}
