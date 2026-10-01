<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Model\Provider;

use Magento\Catalog\Model\Product\Visibility as ProductVisibility;
use Ergonode\Core\Api\MappingVisibilityProviderInterface;

class MagentoVisibilityOptionProvider
{
    public const string ATTRIBUTE_CODE = 'visibility';

    /**
     * @var array<int, array{label: string, code: string, scope: string, type: string, active: bool}>|null
     */
    private ?array $optionsCache = null;

    public function __construct(
        private readonly MappingVisibilityProviderInterface $visibilityProvider
    ) {
    }

    public function isSupported(string $attributeCode): bool
    {
        return $attributeCode === self::ATTRIBUTE_CODE;
    }

    /**
     * @return array<int, array{label: string, code: string, scope: string, type: string, active: bool}>
     */
    public function getOptions(): array
    {
        if ($this->optionsCache !== null) {
            return $this->optionsCache;
        }

        $options = [];
        $codes = [];

        foreach (ProductVisibility::getAllOptions() as $option) {
            $value = (string)($option['value'] ?? '');
            $label = (string)($option['label'] ?? '');

            if ($value === '' || $value === '0' || $label === '') {
                continue;
            }

            $code = 'option_' . $value;
            $codes[] = $code;
            $options[] = [
                'label' => $label,
                'code' => $code,
                'scope' => 'VALUE ' . $value,
                'type' => 'option',
                'active' => true,
            ];
        }

        $activeMap = $this->visibilityProvider->getActiveMap(
            'option',
            'magento',
            $codes,
            self::ATTRIBUTE_CODE
        );
        foreach ($options as &$option) {
            $option['active'] = $activeMap[$option['code']] ?? true;
        }
        unset($option);

        return $this->optionsCache = $options;
    }
}
