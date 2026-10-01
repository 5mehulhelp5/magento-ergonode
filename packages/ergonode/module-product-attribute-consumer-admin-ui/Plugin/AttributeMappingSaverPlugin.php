<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumerAdminUi\Plugin;

use Ergonode\ProductAttributeAdminUi\Model\Mapping\AttributeMappingSaver;
use Ergonode\ProductAttributeConsumer\Model\Mapping\AttributeMappingSaver as InboundMappingSaver;

class AttributeMappingSaverPlugin
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
        AttributeMappingSaver $subject,
        callable $proceed,
        array $mappings,
        array $visibility
    ): array {
        foreach ($mappings as $mapping) {
            $right = $mapping['right'] ?? null;
            if (is_array($right) && (!empty($right['pending_create'])
                || str_starts_with((string)($right['code'] ?? ''), 'pending_'))
            ) {
                return $this->mappingSaver->save($mappings, $visibility);
            }
        }

        return $proceed($mappings, $visibility);
    }
}
