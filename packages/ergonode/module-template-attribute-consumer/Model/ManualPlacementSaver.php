<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Model;

use Ergonode\TemplateAttribute\Api\StructureProviderInterface;
use Ergonode\TemplateAttributeConsumer\Api\ManualPlacementSaverInterface;
use Magento\Framework\Exception\LocalizedException;

class ManualPlacementSaver implements ManualPlacementSaverInterface
{
    public function __construct(
        private readonly StructureProviderInterface $structureProvider,
        private readonly ManualPlacementResource $resource
    ) {
    }

    public function save(string $templateCode, int $attributeSetId, int $attributeId, bool $manual): void
    {
        $structure = $this->structureProvider->get($templateCode, $attributeSetId);
        foreach ($structure['attributes'] as $attribute) {
            if ((int)$attribute['attribute_id'] === $attributeId
                && $attribute['attribute_group_id'] !== null
                && $attribute['ergonode_attribute_codes'] !== []
                && empty($attribute['placement_protected'])
            ) {
                $this->resource->save($attributeSetId, $attributeId, $manual);
                return;
            }
        }
        throw new LocalizedException(__(
            'Manual placement is available only for mapped, unprotected attributes in this set.'
        ));
    }
}
