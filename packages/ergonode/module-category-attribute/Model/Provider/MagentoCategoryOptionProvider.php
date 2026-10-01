<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttribute\Model\Provider;

use Ergonode\Core\Api\MappingVisibilityProviderInterface;
use Magento\Catalog\Model\Category;
use Magento\Eav\Api\AttributeOptionManagementInterface;
use Magento\Eav\Model\Config as EavConfig;
use Ergonode\CategoryAttribute\Api\MagentoOptionProviderInterface;

class MagentoCategoryOptionProvider implements MagentoOptionProviderInterface
{
    public function __construct(
        private readonly AttributeOptionManagementInterface $optionManagement,
        private readonly EavConfig $eavConfig,
        private readonly MappingVisibilityProviderInterface $visibilityProvider
    ) {
    }

    /** @return array<int, array{label: string, code: string, scope: string, type: string, active: bool}> */
    public function getOptions(string $attributeCode): array
    {
        $entityTypeId = (int)$this->eavConfig->getEntityType(Category::ENTITY)->getEntityTypeId();
        $options = [];
        $codes = [];
        foreach ($this->optionManagement->getItems($entityTypeId, $attributeCode) as $option) {
            $value = trim((string)$option->getValue());
            $label = trim((string)$option->getLabel());
            if ($value === '' || $label === '') {
                continue;
            }
            $code = 'option_' . $value;
            $codes[] = $code;
            $options[] = [
                'label' => $label,
                'code' => $code,
                'scope' => 'ID ' . $value,
                'type' => 'option',
                'active' => true,
            ];
        }

        $active = $this->visibilityProvider->getActiveMap(
            'category_option',
            'magento',
            $codes,
            $attributeCode
        );
        foreach ($options as &$option) {
            $option['active'] = $active[$option['code']] ?? true;
        }
        unset($option);

        return $options;
    }
}
