<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Model\Mapping;

use Ergonode\Attribute\Api\AttributeTypeCompatibilityInterface;
use Ergonode\ProductAttribute\Model\Config\ProductAttributePolicy;

class ProductAttributeMappingCompatibility
{
    public function __construct(
        private readonly AttributeTypeCompatibilityInterface $typeCompatibility,
        private readonly ProductAttributePolicy $attributePolicy
    ) {
    }

    public function canMapAttributes(
        string $ergonodeType,
        string $magentoType,
        string $magentoAttributeCode
    ): bool {
        if (!$this->attributePolicy->isTypeMappable($ergonodeType)
            || (strtolower($ergonodeType) === 'image' && strtolower($magentoType) !== 'image')
            || !$this->typeCompatibility->canMapAttributes($ergonodeType, $magentoType)
        ) {
            return false;
        }

        $allowedTypes = $this->attributePolicy->getAllowedErgonodeTypes($magentoAttributeCode);
        if ($allowedTypes === null) {
            return true;
        }

        return in_array(strtolower(trim($ergonodeType)), $allowedTypes, true);
    }

    /**
     * @return array<string, string[]>
     */
    public function getAttributeCompatibilityMap(): array
    {
        $map = $this->typeCompatibility->getAttributeCompatibilityMap();
        foreach ($map as $type => $targets) {
            if (!$this->attributePolicy->isTypeMappable($type)) {
                $map[$type] = [];
            } elseif ($type === 'image') {
                $map[$type] = ['image'];
            }
        }
        return $map;
    }

    /**
     * @return array<string, string[]>
     */
    public function getMagentoAttributeTypeConstraints(): array
    {
        return $this->attributePolicy->getErgonodeTypeConstraints();
    }
}
