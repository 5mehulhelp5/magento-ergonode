<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttribute\Model\Provider;

use Ergonode\Attribute\Api\MagentoAttributeTypeResolverInterface;
use Ergonode\CategoryAttribute\Api\MappingPolicyInterface;
use Ergonode\Core\Api\MappingVisibilityProviderInterface;
use Magento\Catalog\Model\ResourceModel\Category\Attribute\CollectionFactory;
use Magento\Eav\Model\Entity\Attribute;
use Ergonode\CategoryAttribute\Api\MagentoAttributeProviderInterface;

class MagentoCategoryAttributeProvider implements MagentoAttributeProviderInterface
{
    private const array ALLOWED_NATIVE_CODES = [
        'default_sort_by',
        'description',
        'image',
        'include_in_menu',
        'is_active',
        'is_anchor',
        'meta_description',
        'meta_keywords',
        'meta_title',
        'name',
        'url_key',
    ];

    /** @var array<int, array<string, mixed>>|null */
    private ?array $attributes = null;

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly MappingVisibilityProviderInterface $visibilityProvider,
        private readonly MagentoAttributeTypeResolverInterface $typeResolver,
        private readonly MappingPolicyInterface $attributePolicy
    ) {
    }

    /**
     * @return array<int, array{
     *     label: string,
     *     code: string,
     *     scope: string,
     *     type: string,
     *     active: bool,
     *     required: bool,
     *     has_custom_source: bool
     * }>
     */
    public function getAttributes(): array
    {
        if ($this->attributes !== null) {
            return $this->attributes;
        }

        $attributes = [];
        $codes = [];
        foreach ($this->collectionFactory->create() as $attribute) {
            if (!$attribute instanceof Attribute || !$this->isAvailable($attribute)) {
                continue;
            }

            $code = trim((string)$attribute->getAttributeCode());
            $required = $this->attributePolicy->isRequiredMapping(
                $code,
                (bool)$attribute->getIsRequired()
            );
            $codes[] = $code;
            $attributes[] = [
                'label' => (string)($attribute->getDefaultFrontendLabel() ?: $code),
                'code' => $code,
                'scope' => (int)$attribute->getData('is_global') === 0 ? 'store view' : 'global',
                'type' => $this->resolveType($attribute),
                'active' => true,
                'required' => $required,
                'has_custom_source' => trim((string)$attribute->getSourceModel()) !== '',
            ];
        }

        $activeMap = $this->visibilityProvider->getActiveMap('category_attribute', 'magento', $codes);
        foreach ($attributes as &$attribute) {
            $attribute['active'] = $attribute['required'] || ($activeMap[$attribute['code']] ?? true);
        }
        unset($attribute);

        return $this->attributes = $attributes;
    }

    /** @return array<string, array<string, mixed>> */
    public function getAttributeMap(): array
    {
        $result = [];
        foreach ($this->getAttributes() as $attribute) {
            $result[(string)$attribute['code']] = $attribute;
        }

        return $result;
    }

    /** @return array<string, mixed>|null */
    public function getAttribute(string $code): ?array
    {
        return $this->getAttributeMap()[$code] ?? null;
    }

    private function isAvailable(Attribute $attribute): bool
    {
        $code = trim((string)$attribute->getAttributeCode());
        $label = trim((string)$attribute->getDefaultFrontendLabel());
        if ($code === '' || $label === '' || (string)$attribute->getBackendType() === 'static') {
            return false;
        }
        if (!$this->attributePolicy->isMappable($code)) {
            return false;
        }

        if ((bool)$attribute->getIsUserDefined()) {
            return true;
        }

        return in_array($code, self::ALLOWED_NATIVE_CODES, true);
    }

    private function resolveType(Attribute $attribute): string
    {
        $frontendInput = (string)$attribute->getFrontendInput();

        return $frontendInput === 'image'
            ? 'image'
            : $this->typeResolver->fromStorage(
                $frontendInput,
                (string)$attribute->getBackendType(),
                (string)$attribute->getSourceModel()
            );
    }
}
