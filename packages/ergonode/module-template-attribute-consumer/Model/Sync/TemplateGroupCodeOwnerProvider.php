<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Model\Sync;

class TemplateGroupCodeOwnerProvider
{
    public function __construct(
        private readonly TemplateStructureOwnershipResource $ownershipResource
    ) {
    }

    /**
     * @return array{attribute_group_id: int, template_code: string|null, section_code: string|null}|null
     */
    public function find(int $attributeSetId, string $groupCode): ?array
    {
        return $this->ownershipResource->findGroupCodeOwner($attributeSetId, $groupCode);
    }
}
