<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisher\Model\Source;

use Ergonode\AttributePublisher\Api\AttributeDesiredStateFactoryInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeOptionStateInterface;
use Ergonode\Language\Model\LocalizedStoreProjection;
use Ergonode\ProductAttribute\Api\MappingReaderInterface;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Eav\Api\Data\AttributeOptionInterface;
use Magento\Framework\Exception\LocalizedException;

class OptionSourceStateBuilder
{
    public function __construct(
        private readonly AttributeDesiredStateFactoryInterface $stateFactory,
        private readonly LocalizedStoreProjection $localizedProjection,
        private readonly SystemOptionDefinition $systemOptionDefinition,
        private readonly OptionCodeGenerator $optionCodeGenerator,
        private readonly MappingReaderInterface $mappingReader
    ) {
    }

    /**
     * @param array<int|string, int> $optionIds
     * @return AttributeOptionStateInterface[]
     */
    public function build(ProductAttributeInterface $attribute, string $ergonodeCode, array $optionIds): array
    {
        $optionsById = [];
        foreach ($attribute->getOptions() ?? [] as $option) {
            $value = trim((string)$option->getValue());
            if ($value !== '' && ctype_digit($value)) {
                $optionsById[(int)$value] = $option;
            }
        }
        $mappedCodes = $this->mappedCodes((string)$attribute->getAttributeCode(), $ergonodeCode);
        $codesByOption = $this->codesByOption($attribute, $optionsById, $optionIds, $mappedCodes);
        $optionIdsByCode = [];
        foreach ($codesByOption as $id => $optionCode) {
            $optionIdsByCode[$optionCode][] = $id;
        }
        $states = [];
        foreach ($optionIds as $code => $optionId) {
            $option = $optionsById[$optionId] ?? null;
            if (!$option instanceof AttributeOptionInterface) {
                throw new LocalizedException(__(
                    'Magento option ID "%1" for attribute "%2" does not exist.',
                    $optionId,
                    $attribute->getAttributeCode()
                ));
            }
            $system = $this->systemOptionDefinition->get($attribute, $optionId);
            $isGenerated = !is_string($code) && !isset($mappedCodes[$optionId]) && $system === null;
            $code = is_string($code) ? $code : ($mappedCodes[$optionId] ?? $system['code']
                ?? $this->optionCodeGenerator->generate((string)$option->getLabel()));
            if ($isGenerated && count($optionIdsByCode[$code] ?? []) > 1) {
                throw new LocalizedException(
                    __('Ergonode option code "%1" is generated for more than one Magento option.', $code)
                );
            }
            $states[] = $this->stateFactory->createOption($code, $system['names'] ?? $this->names($option));
        }

        return $states;
    }

    /**
     * @param array<int, AttributeOptionInterface> $optionsById
     * @param array<int|string, int> $requestedOptionIds
     * @param array<int, string> $mappedCodes
     * @return array<int, string>
     */
    private function codesByOption(
        ProductAttributeInterface $attribute,
        array $optionsById,
        array $requestedOptionIds,
        array $mappedCodes
    ): array {
        $explicitCodes = [];
        foreach ($requestedOptionIds as $code => $optionId) {
            if (is_string($code)) {
                $explicitCodes[$optionId] = $code;
            }
        }
        $result = [];
        foreach ($optionsById as $optionId => $option) {
            $system = $this->systemOptionDefinition->get($attribute, $optionId);
            try {
                $result[$optionId] = $explicitCodes[$optionId] ?? $mappedCodes[$optionId] ?? $system['code']
                    ?? $this->optionCodeGenerator->generate((string)$option->getLabel());
            } catch (LocalizedException) {
                // Invalid labels cannot reserve a code; requested options still fail below.
            }
        }

        return $result;
    }

    /** @return array<string, string> */
    private function names(AttributeOptionInterface $option): array
    {
        $storeLabels = [];
        foreach ($option->getStoreLabels() ?? [] as $label) {
            $storeLabels[(int)$label->getStoreId()] = (string)$label->getLabel();
        }

        return $this->localizedProjection->project((string)$option->getLabel(), $storeLabels);
    }

    /** @return array<int, string> */
    private function mappedCodes(string $magentoCode, string $ergonodeCode): array
    {
        foreach ($this->mappingReader->getAttributeRows() as $row) {
            if (($row['magento_attribute_code'] ?? '') !== $magentoCode
                || ($row['ergonode_attribute_code'] ?? '') !== $ergonodeCode) {
                continue;
            }
            $result = [];
            foreach ($this->mappingReader->getOptionRows((int)$row['mapping_id']) as $option) {
                $code = trim((string)($option['ergonode_option_code'] ?? ''));
                if ($code !== '' && isset($option['magento_option_id'])) {
                    $result[(int)$option['magento_option_id']] = $code;
                }
            }

            return $result;
        }

        return [];
    }
}
