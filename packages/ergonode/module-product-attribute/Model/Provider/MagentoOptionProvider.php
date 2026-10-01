<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Model\Provider;

use Magento\Eav\Api\AttributeOptionManagementInterface;
use Ergonode\Core\Api\MappingVisibilityProviderInterface;
use Ergonode\ProductAttribute\Model\Provider\MagentoAttributeProvider;
use Magento\Framework\App\ResourceConnection;

class MagentoOptionProvider
{
    private const string ENTITY_TYPE = 'catalog_product';

    /**
     * @var array<string, array<int, array{label: string, code: string, scope: string, type: string,
     *     active: bool, store_labels?: array<int, string>}>>
     */
    private array $optionsCache = [];

    public function __construct(
        private readonly AttributeOptionManagementInterface $attributeOptionManagement,
        private readonly MagentoVisibilityOptionProvider $visibilityOptionProvider,
        private readonly MagentoAttributeProvider $magentoAttributeProvider,
        private readonly MappingVisibilityProviderInterface $visibilityProvider,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * @return array<int, array{label: string, code: string, scope: string, type: string,
     *     active: bool, store_labels?: array<int, string>}>
     */
    public function getOptions(string $attributeCode): array
    {
        if (isset($this->optionsCache[$attributeCode])) {
            return $this->optionsCache[$attributeCode];
        }

        if ($this->visibilityOptionProvider->isSupported($attributeCode)) {
            return $this->optionsCache[$attributeCode] = $this->visibilityOptionProvider->getOptions();
        }

        $attribute = $this->magentoAttributeProvider->getAttribute($attributeCode, true);
        $hasCustomSource = !empty($attribute['has_custom_source']);

        if (($attribute['type'] ?? '') === 'boolean') {
            return $this->optionsCache[$attributeCode] = $this->getBooleanOptions($attributeCode);
        }

        $options = [];
        $codes = [];
        $optionIndexes = [];

        foreach ($this->attributeOptionManagement->getItems(self::ENTITY_TYPE, $attributeCode) as $option) {
            $value = (string)$option->getValue();
            $label = (string)$option->getLabel();

            if ($value === '' || $value === '0' || $label === '') {
                continue;
            }

            $code = 'option_' . $value;
            $codes[] = $code;
            if (!$hasCustomSource && ctype_digit($value)) {
                $optionIndexes[(int)$value] = count($options);
            }
            $options[] = [
                'label' => !$hasCustomSource && ctype_digit($value) ? '' : $label,
                'code' => $code,
                'scope' => 'ID ' . $value,
                'type' => 'option',
                'active' => true,
                'store_labels' => [],
            ];
        }

        if ($optionIndexes !== []) {
            $connection = $this->resourceConnection->getConnection();
            $rows = $connection->fetchAll(
                $connection->select()
                    ->from(
                        $this->resourceConnection->getTableName('eav_attribute_option_value'),
                        ['option_id', 'store_id', 'value']
                    )
                    ->where('option_id IN (?)', array_keys($optionIndexes))
            );
            foreach ($rows as $row) {
                $index = $optionIndexes[(int)$row['option_id']] ?? null;
                if ($index === null) {
                    continue;
                }
                $storeId = (int)$row['store_id'];
                if ($storeId === 0) {
                    $options[$index]['label'] = (string)$row['value'];
                } elseif ($storeId > 0) {
                    $options[$index]['store_labels'][$storeId] = (string)$row['value'];
                }
            }
        }

        $activeMap = $this->visibilityProvider->getActiveMap('option', 'magento', $codes, $attributeCode);
        foreach ($options as &$option) {
            $option['active'] = $activeMap[$option['code']] ?? true;
        }
        unset($option);

        return $this->optionsCache[$attributeCode] = $options;
    }

    /**
     * @return array<int, array{label: string, code: string, scope: string, type: string, active: bool}>
     */
    private function getBooleanOptions(string $attributeCode): array
    {
        $activeMap = $this->visibilityProvider->getActiveMap(
            'option',
            'magento',
            ['option_0', 'option_1'],
            $attributeCode
        );

        return [
            [
                'label' => 'No',
                'code' => 'option_0',
                'scope' => 'VALUE 0',
                'type' => 'option',
                'active' => $activeMap['option_0'] ?? true,
            ],
            [
                'label' => 'Yes',
                'code' => 'option_1',
                'scope' => 'VALUE 1',
                'type' => 'option',
                'active' => $activeMap['option_1'] ?? true,
            ],
        ];
    }
}
