<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisher\Model\Provider;

use Ergonode\AttributePublisher\Api\AttributeDesiredStateFactoryInterface;
use Ergonode\AttributePublisher\Api\AttributeTypeResolverInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeOptionStateInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeStateInterface;
use Ergonode\CategoryAttributePublisher\Api\MagentoOptionLabelReaderInterface;
use Ergonode\Language\Model\LocalizedStoreProjection;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Exception\LocalizedException;

class CategoryAttributeSourceStateBuilder
{
    public function __construct(
        private readonly EavConfig $eavConfig,
        private readonly AttributeDesiredStateFactoryInterface $stateFactory,
        private readonly AttributeTypeResolverInterface $typeResolver,
        private readonly LocalizedStoreProjection $localizedProjection,
        private readonly MagentoOptionLabelReaderInterface $optionLabelReader
    ) {
    }

    public function build(string $attributeCode, string $targetType): AttributeStateInterface
    {
        $attributeCode = trim($attributeCode);
        $attribute = $this->eavConfig->getAttribute(Category::ENTITY, $attributeCode);
        if (!$attribute instanceof Attribute || !(int)$attribute->getAttributeId()) {
            throw new LocalizedException(__('Magento category attribute "%1" does not exist.', $attributeCode));
        }
        $sourceType = $this->sourceType($attribute);
        $ergonodeType = $this->typeResolver->resolve(trim($targetType) ?: $sourceType);
        if ($ergonodeType === null) {
            throw new LocalizedException(__(
                'Magento category attribute type "%1" cannot be created in Ergonode.',
                $sourceType
            ));
        }

        return $this->stateFactory->createAttribute(
            $attributeCode,
            $ergonodeType,
            $attribute->getScope() === Attribute::SCOPE_GLOBAL_TEXT ? 'GLOBAL' : 'LOCAL',
            $this->names($attribute),
            $this->parameters($attribute, $ergonodeType),
            [],
            $this->options($attribute, $ergonodeType)
        );
    }

    private function sourceType(Attribute $attribute): string
    {
        $frontendInput = strtolower(trim((string)$attribute->getFrontendInput()));
        if ($frontendInput === 'media_image') {
            return 'image';
        }
        if (in_array($frontendInput, [
            'boolean', 'date', 'file', 'image', 'multiselect', 'price', 'select', 'textarea', 'unit',
        ], true)) {
            return $frontendInput;
        }

        return in_array((string)$attribute->getBackendType(), ['decimal', 'int'], true) ? 'decimal' : 'text';
    }

    /** @return array<string, string> */
    private function names(Attribute $attribute): array
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
    private function parameters(Attribute $attribute, string $type): array
    {
        return match ($type) {
            'date' => ['format' => 'yyyy-MM-dd'],
            'numeric', 'text' => ['unique' => (bool)$attribute->getIsUnique()],
            'textarea' => ['richEdit' => (bool)$attribute->getIsWysiwygEnabled()],
            'unit' => ['unitName' => $this->unitName($attribute)],
            default => [],
        };
    }

    private function unitName(Attribute $attribute): string
    {
        $unitName = trim((string)$attribute->getData('ergonode_unit_name'));
        if ($unitName === '') {
            throw new LocalizedException(__(
                'Magento category unit attribute "%1" does not have an Ergonode unit name.',
                $attribute->getAttributeCode()
            ));
        }

        return $unitName;
    }

    /** @return AttributeOptionStateInterface[] */
    private function options(Attribute $attribute, string $type): array
    {
        if (!in_array($type, ['select', 'multi_select'], true)) {
            return [];
        }
        $options = [];
        $persistedLabels = $this->optionLabelReader->read((int)$attribute->getAttributeId());
        foreach ($attribute->getOptions() ?? [] as $option) {
            $id = trim((string)$option->getValue());
            $storeLabels = $persistedLabels[(int)$id] ?? [];
            foreach ($option->getStoreLabels() ?? [] as $storeLabel) {
                $storeLabels[(int)$storeLabel->getStoreId()] = (string)$storeLabel->getLabel();
            }
            $label = trim($storeLabels[0] ?? (string)$option->getLabel());
            if ($id === '' || !ctype_digit($id) || $label === '') {
                continue;
            }
            $options[] = $this->stateFactory->createOption(
                'option_' . $id,
                $this->localizedProjection->project($label, $storeLabels)
            );
        }

        return $options;
    }
}
