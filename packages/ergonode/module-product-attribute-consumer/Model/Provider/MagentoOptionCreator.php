<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Model\Provider;

use Ergonode\ProductAttribute\Model\Provider\MagentoVisibilityOptionProvider;

use Magento\Catalog\Api\ProductAttributeOptionManagementInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Eav\Api\Data\AttributeOptionInterface;
use Magento\Eav\Api\Data\AttributeOptionInterfaceFactory;
use Magento\Framework\Exception\LocalizedException;

class MagentoOptionCreator
{
    public function __construct(
        private readonly ProductAttributeRepositoryInterface $attributeRepository,
        private readonly ProductAttributeOptionManagementInterface $optionManagement,
        private readonly AttributeOptionInterfaceFactory $optionFactory,
        private readonly MagentoVisibilityOptionProvider $visibilityOptionProvider
    ) {
    }

    /**
     * @return array{
     *     option_id: int,
     *     label: string,
     *     code: string,
     *     scope: string,
     *     type: string,
     *     source: string,
     *     created: bool
     * }
     * @throws LocalizedException
     */
    public function create(string $attributeCode, string $label, int $sortOrder = 0): array
    {
        $attributeCode = trim($attributeCode);
        $label = trim($label);

        if ($attributeCode === '') {
            throw new LocalizedException(__('Magento attribute code is required.'));
        }
        if ($label === '') {
            throw new LocalizedException(__('Magento option label is required.'));
        }
        if ($this->visibilityOptionProvider->isSupported($attributeCode)) {
            throw new LocalizedException(
                __(
                    'Options for the Visibility attribute are provided by Magento source model ' .
                    'and cannot be created here.'
                )
            );
        }

        $attribute = $this->attributeRepository->get($attributeCode);
        $frontendInput = (string)$attribute->getFrontendInput();

        if (!in_array($frontendInput, ['select', 'multiselect'], true)) {
            throw new LocalizedException(__('Magento attribute "%1" does not support options.', $attributeCode));
        }

        $options = $this->optionManagement->getItems($attributeCode);
        $existing = $this->findByLabel($options, $label);
        if ($existing) {
            return $this->formatOption($existing, false);
        }

        $option = $this->optionFactory->create();
        $option->setLabel($label);
        $option->setSortOrder(max(0, $sortOrder));
        $optionId = (string)$this->optionManagement->add($attributeCode, $option);
        $options = $this->optionManagement->getItems($attributeCode);
        $created = $this->findById($options, $optionId) ?: $this->findByLabel($options, $label);

        if (!$created) {
            throw new LocalizedException(__('Magento option has been created but could not be reloaded.'));
        }

        return $this->formatOption($created, true);
    }

    /**
     * @param AttributeOptionInterface[] $options
     */
    private function findById(array $options, string $optionId): ?AttributeOptionInterface
    {
        foreach ($options as $option) {
            if ((string)$option->getValue() === $optionId) {
                return $option;
            }
        }

        return null;
    }

    /**
     * @param AttributeOptionInterface[] $options
     */
    private function findByLabel(array $options, string $label): ?AttributeOptionInterface
    {
        $found = null;
        foreach ($options as $option) {
            if ($this->normalizeLabel((string)$option->getLabel()) === $this->normalizeLabel($label)) {
                if ($found !== null && (string)$found->getValue() !== (string)$option->getValue()) {
                    throw new AmbiguousOptionLabelException(
                        __('Magento has more than one option labeled "%1"; select the option manually.', $label)
                    );
                }
                $found = $option;
            }
        }

        return $found;
    }

    /**
     * @return array{
     *     option_id: int,
     *     label: string,
     *     code: string,
     *     scope: string,
     *     type: string,
     *     source: string,
     *     created: bool
     * }
     */
    private function formatOption(AttributeOptionInterface $option, bool $created): array
    {
        $value = (string)$option->getValue();

        if ($value === '' || $value === '0') {
            throw new LocalizedException(__('Magento returned an invalid option identifier.'));
        }

        return [
            'option_id' => (int)$value,
            'label' => (string)$option->getLabel(),
            'code' => 'option_' . $value,
            'scope' => 'ID ' . $value,
            'type' => 'option',
            'source' => 'magento',
            'created' => $created,
        ];
    }

    private function normalizeLabel(string $label): string
    {
        return mb_strtolower(trim($label));
    }
}
