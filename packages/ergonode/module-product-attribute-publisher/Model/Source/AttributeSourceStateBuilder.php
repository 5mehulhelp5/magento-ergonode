<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisher\Model\Source;

use Ergonode\AttributePublisher\Api\AttributeDesiredStateFactoryInterface;
use Ergonode\ProductAttributePublisher\Api\AttributeSourceConfigurationInterface;
use Ergonode\ProductAttributePublisher\Api\AttributeSourceStateBuilderInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeOptionStateInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeStateInterface;
use Ergonode\Language\Model\LocalizedStoreProjection;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use PackHauer\UnitAttribute\Api\UnitAttributeMetadataInterface;

class AttributeSourceStateBuilder implements AttributeSourceStateBuilderInterface
{
    private const string MAGENTO_PRODUCT_SKU = 'sku';

    public function __construct(
        private readonly ProductAttributeRepositoryInterface $attributeRepository,
        private readonly AttributeDesiredStateFactoryInterface $stateFactory,
        private readonly LocalizedStoreProjection $localizedProjection,
        private readonly AttributeSourceConfigurationInterface $configuration,
        private readonly UnitAttributeMetadataInterface $unitMetadata,
        private readonly OptionSourceStateBuilder $optionStateBuilder
    ) {
    }

    public function build(
        string $ergonodeAttributeCode,
        string $magentoAttributeCode,
        string $ergonodeType,
        array $optionIds
    ): AttributeStateInterface {
        $attribute = $this->attributeRepository->get($magentoAttributeCode);
        $isProductSku = $magentoAttributeCode === self::MAGENTO_PRODUCT_SKU;
        $ergonodeType = $isProductSku ? 'text' : $ergonodeType;

        return $this->stateFactory->createAttribute(
            $ergonodeAttributeCode,
            $ergonodeType,
            $isProductSku ? 'GLOBAL' : $this->scope($attribute),
            $this->names($attribute),
            $this->parameters($attribute, $ergonodeType, $magentoAttributeCode),
            [],
            $this->options($attribute, $ergonodeAttributeCode, $ergonodeType, $optionIds)
        );
    }

    private function scope(ProductAttributeInterface $attribute): string
    {
        return $attribute->getScope() === ProductAttributeInterface::SCOPE_GLOBAL_TEXT ? 'GLOBAL' : 'LOCAL';
    }

    /** @return array<string, string> */
    private function names(ProductAttributeInterface $attribute): array
    {
        $storeLabels = [];
        foreach ($attribute->getFrontendLabels() ?? [] as $label) {
            $storeLabels[(int)$label->getStoreId()] = (string)$label->getLabel();
        }

        return $this->localizedProjection->project(
            (string)$attribute->getDefaultFrontendLabel(),
            $storeLabels
        );
    }

    /** @return array<string, bool|string> */
    private function parameters(ProductAttributeInterface $attribute, string $type, string $magentoCode): array
    {
        if ($magentoCode === self::MAGENTO_PRODUCT_SKU) {
            return ['unique' => true];
        }

        return match ($type) {
            'price' => ['currency' => $this->configuration->getPriceCurrency()],
            'unit' => ['unitName' => $this->unitName($magentoCode)],
            'text', 'numeric' => ['unique' => (bool)$attribute->getIsUnique()],
            'textarea' => ['richEdit' => (bool)$attribute->getIsWysiwygEnabled()],
            'date' => ['format' => 'yyyy-MM-dd'],
            default => [],
        };
    }

    private function unitName(string $attributeCode): string
    {
        $unit = $this->unitMetadata->get($attributeCode);
        if ($unit === null) {
            throw new LocalizedException(__(
                'Magento unit attribute "%1" does not have Ergonode unit metadata.',
                $attributeCode
            ));
        }

        return $unit['name'];
    }

    /**
     * @param array<int|string, int> $optionIds
     * @return AttributeOptionStateInterface[]
     */
    private function options(
        ProductAttributeInterface $attribute,
        string $ergonodeAttributeCode,
        string $ergonodeType,
        array $optionIds
    ): array {
        if (!in_array($ergonodeType, ['select', 'multi_select'], true)) {
            return [];
        }
        return $this->optionStateBuilder->build($attribute, $ergonodeAttributeCode, $optionIds);
    }
}
