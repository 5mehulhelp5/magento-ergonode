<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\Config;

use Ergonode\CategoryConsumer\Api\CategoryNameTargetProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Ergonode\CategoryAttribute\Api\MappingPolicyInterface;

class CategoryAttributePolicy implements MappingPolicyInterface
{
    public const string MODE_MAPPING = 'mapping';

    public const string MODE_MANUAL = 'manual';

    private const array MANAGED_ATTRIBUTES = [
        'name' => [
            'mode' => CategoryAttributeConfigProvider::XML_PATH_NAME_MODE,
        ],
        'include_in_menu' => [
            'mode' => CategoryAttributeConfigProvider::XML_PATH_INCLUDE_IN_MENU_MODE,
            'default' => CategoryAttributeConfigProvider::XML_PATH_INCLUDE_IN_MENU_DEFAULT,
        ],
        'is_active' => [
            'mode' => CategoryAttributeConfigProvider::XML_PATH_IS_ACTIVE_MODE,
            'default' => CategoryAttributeConfigProvider::XML_PATH_IS_ACTIVE_DEFAULT,
        ],
    ];

    private const array CREATION_ATTRIBUTES = [
        'include_in_menu',
        'is_active',
    ];

    /** @param string[] $excludedAttributeCodes */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly CategoryNameTargetProviderInterface $nameTargetProvider,
        private readonly array $excludedAttributeCodes = []
    ) {
    }

    public function isMappable(string $attributeCode): bool
    {
        $attributeCode = trim($attributeCode);
        if ($attributeCode === $this->nameTargetProvider->getAttributeCode()) {
            return false;
        }
        if ($attributeCode === '' || in_array($attributeCode, $this->excludedAttributeCodes, true)) {
            return false;
        }

        return !isset(self::MANAGED_ATTRIBUTES[$attributeCode])
            || $this->mode($attributeCode) === self::MODE_MAPPING;
    }

    public function isRequiredMapping(string $attributeCode, bool $nativeRequired): bool
    {
        if (!$this->isMappable($attributeCode)) {
            return false;
        }

        return isset(self::MANAGED_ATTRIBUTES[$attributeCode]) || $nativeRequired;
    }

    /** @return array<string, int> */
    public function getManualCreationValues(): array
    {
        $values = [];
        foreach (self::CREATION_ATTRIBUTES as $attributeCode) {
            $paths = self::MANAGED_ATTRIBUTES[$attributeCode];
            if ($this->mode($attributeCode) !== self::MODE_MANUAL) {
                continue;
            }
            $values[$attributeCode] = $this->scopeConfig->isSetFlag($paths['default']) ? 1 : 0;
        }

        return $values;
    }

    /** @return string[] */
    public function getMappedCreationAttributeCodes(): array
    {
        $codes = [];
        foreach (self::CREATION_ATTRIBUTES as $attributeCode) {
            if ($this->mode($attributeCode) === self::MODE_MAPPING) {
                $codes[] = $attributeCode;
            }
        }

        return $codes;
    }

    private function mode(string $attributeCode): string
    {
        $mode = strtolower(trim((string)$this->scopeConfig->getValue(
            self::MANAGED_ATTRIBUTES[$attributeCode]['mode']
        )));

        return $mode === self::MODE_MANUAL ? self::MODE_MANUAL : self::MODE_MAPPING;
    }
}
