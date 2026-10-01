<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\Provider;

use Ergonode\CategoryAttributeConsumer\Api\MagentoOptionCreatorInterface;

use Magento\Catalog\Model\Category;
use Magento\Eav\Api\AttributeOptionManagementInterface;
use Magento\Eav\Api\Data\AttributeOptionInterface;
use Magento\Eav\Api\Data\AttributeOptionInterfaceFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Exception\LocalizedException;

class MagentoCategoryOptionCreator implements MagentoOptionCreatorInterface
{
    public function __construct(
        private readonly AttributeOptionManagementInterface $optionManagement,
        private readonly AttributeOptionInterfaceFactory $optionFactory,
        private readonly EavConfig $eavConfig
    ) {
    }

    /** @return array{label: string, code: string, scope: string, type: string, source: string, created: bool} */
    public function create(string $attributeCode, string $label): array
    {
        $attributeCode = trim($attributeCode);
        $label = trim($label);
        if ($attributeCode === '' || $label === '') {
            throw new LocalizedException(__('Magento category attribute and option label are required.'));
        }

        $entityTypeId = (int)$this->eavConfig->getEntityType(Category::ENTITY)->getEntityTypeId();
        $options = $this->optionManagement->getItems($entityTypeId, $attributeCode);
        $existing = $this->findByLabel($options, $label);
        if ($existing !== null) {
            return $this->format($existing, false);
        }

        $option = $this->optionFactory->create();
        $option->setLabel($label);
        $optionId = $this->optionManagement->add($entityTypeId, $attributeCode, $option);
        $created = $this->findById(
            $this->optionManagement->getItems($entityTypeId, $attributeCode),
            (string)$optionId
        );
        if ($created === null) {
            throw new LocalizedException(__('Magento category option has been created but could not be reloaded.'));
        }

        return $this->format($created, true);
    }

    /** @param AttributeOptionInterface[] $options */
    private function findByLabel(array $options, string $label): ?AttributeOptionInterface
    {
        foreach ($options as $option) {
            if (mb_strtolower(trim((string)$option->getLabel())) === mb_strtolower($label)) {
                return $option;
            }
        }

        return null;
    }

    /** @param AttributeOptionInterface[] $options */
    private function findById(array $options, string $id): ?AttributeOptionInterface
    {
        foreach ($options as $option) {
            if ((string)$option->getValue() === $id) {
                return $option;
            }
        }

        return null;
    }

    /** @return array{label: string, code: string, scope: string, type: string, source: string, created: bool} */
    private function format(AttributeOptionInterface $option, bool $created): array
    {
        $value = trim((string)$option->getValue());
        if ($value === '' || $value === '0') {
            throw new LocalizedException(__('Magento returned an invalid category option identifier.'));
        }

        return [
            'label' => (string)$option->getLabel(),
            'code' => 'option_' . $value,
            'scope' => 'ID ' . $value,
            'type' => 'option',
            'source' => 'magento',
            'created' => $created,
        ];
    }
}
