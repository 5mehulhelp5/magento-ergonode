<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Model\Config;

use Ergonode\Product\Api\ProductIdentityModeProviderInterface;
use Ergonode\Product\Api\MagentoIdentityAttributeInterface;
use Ergonode\ProductAttribute\Api\ProductAttributePlacementPolicyInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;

class ProductAttributePolicy
{
    public const string MODE_MAPPING = 'mapping';

    public const string MODE_MANUAL = 'manual';

    public const string MODE_MAGENTO = 'magento';

    public const string XML_PATH_URL_KEY = 'ergonode_products/attributes/url_key';
    public const string XML_PATH_PRICE_MODE = 'ergonode_products/attributes/price_mode';
    public const string XML_PATH_PRICE_DEFAULT = 'ergonode_products/attributes/price_default';
    public const string XML_PATH_STATUS_MODE = 'ergonode_products/attributes/status';
    public const string XML_PATH_STATUS_DEFAULT = 'ergonode_products/attributes/status_default';
    public const string XML_PATH_VISIBILITY_MODE = 'ergonode_products/attributes/visibility';
    public const string XML_PATH_VISIBILITY_DEFAULT = 'ergonode_products/attributes/visibility_default';

    private const string ERGONODE_GALLERY_ATTRIBUTE_CODE = 'gallery';
    private const array ERGONODE_TYPE_CONSTRAINTS = [
        'name' => ['text'],
        'sku' => ['text'],
        'url_key' => ['text'],
        'price' => ['price'],
    ];
    private const array MANAGED_ATTRIBUTES = [
        'price' => [
            'mode' => self::XML_PATH_PRICE_MODE,
            'default' => self::XML_PATH_PRICE_DEFAULT,
        ],
        'status' => [
            'mode' => self::XML_PATH_STATUS_MODE,
            'default' => self::XML_PATH_STATUS_DEFAULT,
        ],
        'visibility' => [
            'mode' => self::XML_PATH_VISIBILITY_MODE,
            'default' => self::XML_PATH_VISIBILITY_DEFAULT,
        ],
    ];

    /** @param array<string,bool> $mediaTypes Media capabilities contributed by optional domain modules. */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ProductAttributePlacementPolicyInterface $placementPolicy,
        private readonly ProductIdentityModeProviderInterface $identityModeProvider,
        private readonly MagentoIdentityAttributeInterface $identityAttribute,
        private readonly array $mediaTypes = []
    ) {
    }

    public function isMappable(string $attributeCode): bool
    {
        $attributeCode = trim($attributeCode);
        if ($attributeCode === '' || $this->placementPolicy->isExcluded($attributeCode)
            || $this->isIdentityAttribute($attributeCode)
        ) {
            return false;
        }

        if ($attributeCode === 'sku'
            || $attributeCode === 'url_key'
            || isset(self::MANAGED_ATTRIBUTES[$attributeCode])
        ) {
            return $this->isMappingRequired($attributeCode);
        }

        return true;
    }

    public function isIdentityAttribute(string $attributeCode): bool
    {
        return trim($attributeCode) !== ''
            && $this->identityModeProvider->getMode() === ProductIdentityModeProviderInterface::MODE_MAPPED
            && trim($attributeCode) === $this->identityAttribute->getCode();
    }

    public function isErgonodeMappable(string $attributeCode): bool
    {
        return strtolower(trim($attributeCode)) !== self::ERGONODE_GALLERY_ATTRIBUTE_CODE;
    }

    public function isTypeMappable(string $type): bool
    {
        $type = strtolower(trim($type));
        if ($type === 'gallery') {
            return false;
        }
        return $type !== 'image' || !empty($this->mediaTypes[$type]);
    }

    public function isTemplatePlacementProtected(string $attributeCode): bool
    {
        return $this->placementPolicy->isProtected($attributeCode);
    }

    public function isMappingRequired(string $attributeCode): bool
    {
        $attributeCode = trim($attributeCode);
        if ($attributeCode === '' || $this->placementPolicy->isExcluded($attributeCode)
            || $this->isIdentityAttribute($attributeCode)
        ) {
            return false;
        }

        if ($attributeCode === 'sku') {
            return $this->identityModeProvider->getMode() === ProductIdentityModeProviderInterface::MODE_ASSIGNED;
        }

        if ($attributeCode === 'url_key') {
            return strtolower(trim((string)$this->scopeConfig->getValue(self::XML_PATH_URL_KEY)))
                === self::MODE_MAPPING;
        }

        return isset(self::MANAGED_ATTRIBUTES[$attributeCode])
            && $this->mode($attributeCode) === self::MODE_MAPPING;
    }

    /**
     * @return string[]|null
     */
    public function getAllowedErgonodeTypes(string $attributeCode): ?array
    {
        return self::ERGONODE_TYPE_CONSTRAINTS[strtolower(trim($attributeCode))] ?? null;
    }

    /**
     * @return array<string, string[]>
     */
    public function getErgonodeTypeConstraints(): array
    {
        return self::ERGONODE_TYPE_CONSTRAINTS;
    }

    /**
     * @return array<string, int|string>
     * @throws LocalizedException
     */
    public function getManualCreationValues(): array
    {
        $values = [];
        foreach (self::MANAGED_ATTRIBUTES as $attributeCode => $paths) {
            if ($this->mode($attributeCode) !== self::MODE_MANUAL) {
                continue;
            }

            $value = trim((string)$this->scopeConfig->getValue($paths['default']));
            if ($value === '') {
                throw new LocalizedException(__(
                    'Default value for Magento product attribute "%1" is not configured.',
                    $attributeCode
                ));
            }

            if ($attributeCode === 'price') {
                if (!is_numeric($value) || !is_finite((float)$value) || (float)$value < 0) {
                    throw new LocalizedException(__('Default product price must be a non-negative decimal number.'));
                }
                $values[$attributeCode] = $value;
                continue;
            }

            $numericValue = (int)$value;
            $allowedValues = $attributeCode === 'status' ? [1, 2] : [1, 2, 3, 4];
            if (!in_array($numericValue, $allowedValues, true) || (string)$numericValue !== $value) {
                throw new LocalizedException(__('%1 has an invalid default value.', $attributeCode));
            }
            $values[$attributeCode] = $numericValue;
        }

        return $values;
    }

    private function mode(string $attributeCode): string
    {
        $mode = strtolower(trim((string)$this->scopeConfig->getValue(
            self::MANAGED_ATTRIBUTES[$attributeCode]['mode']
        )));

        return $mode === self::MODE_MANUAL ? self::MODE_MANUAL : self::MODE_MAPPING;
    }
}
