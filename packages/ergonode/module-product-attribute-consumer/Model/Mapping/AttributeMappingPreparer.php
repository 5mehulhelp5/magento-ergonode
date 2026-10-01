<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Model\Mapping;

use Ergonode\ProductAttribute\Model\Config\ProductAttributePolicy;
use Ergonode\AttributeConsumer\Model\Provider\ErgonodeAttributeProvider;
use Ergonode\ProductAttributeConsumer\Model\Provider\MagentoAttributeCreator;
use Ergonode\ProductAttribute\Model\Provider\MagentoAttributeProvider;
use Magento\Framework\Exception\LocalizedException;

class AttributeMappingPreparer
{
    public function __construct(
        private readonly ErgonodeAttributeProvider $ergonodeAttributeProvider,
        private readonly MagentoAttributeProvider $magentoAttributeProvider,
        private readonly MagentoAttributeCreator $magentoAttributeCreator,
        private readonly ProductAttributePolicy $attributePolicy
    ) {
    }

    /**
     * @param  array<int, array<string, mixed>>    $mappings
     * @param  array<string, array<string, mixed>> $pendingMagentoAttributes
     * @return array<int, array<string, mixed>>
     * @throws LocalizedException
     */
    public function prepare(array $mappings, array &$pendingMagentoAttributes = []): array
    {
        $result = [];

        foreach ($mappings as $mapping) {
            $left = isset($mapping['left']) && is_array($mapping['left']) ? $mapping['left'] : null;
            $right = isset($mapping['right']) && is_array($mapping['right']) ? $mapping['right'] : null;
            $leftCode = $left ? trim((string)($left['code'] ?? '')) : '';
            $rightCode = $right ? trim((string)($right['code'] ?? '')) : '';
            $pendingErgonodeCreate = $left && !empty($left['pending_create']);
            $pendingMagentoCreate = $right && !empty($right['pending_create']);

            if ($leftCode === '' && $rightCode === '') {
                continue;
            }
            if ($leftCode !== '' && !$this->attributePolicy->isErgonodeMappable($leftCode)) {
                throw new LocalizedException(__('Ergonode attribute "%1" is not available for mapping.', $leftCode));
            }

            $leftAttribute = $pendingErgonodeCreate
                ? $left
                : ($leftCode !== '' ? $this->ergonodeAttributeProvider->getAttribute($leftCode) : null);
            if ($leftCode !== '' && !$leftAttribute) {
                throw new LocalizedException(__('Ergonode attribute "%1" is not available for mapping.', $leftCode));
            }
            if ($pendingErgonodeCreate && trim((string)($leftAttribute['type'] ?? '')) === '') {
                throw new LocalizedException(__('Ergonode attribute type is required before creating the attribute.'));
            }

            if ($pendingMagentoCreate) {
                if (!$leftAttribute) {
                    throw new LocalizedException(
                        __('Choose an Ergonode attribute before creating a Magento attribute.')
                    );
                }

                $rightAttribute = $this->magentoAttributeCreator->previewFromErgonodeAttribute($leftAttribute);
                $rightCode = (string)$rightAttribute['code'];
                if (empty($rightAttribute['created']) && !$this->magentoAttributeProvider->getAttribute($rightCode)) {
                    throw new LocalizedException(
                        __('Magento attribute "%1" is not available for mapping.', $rightCode)
                    );
                }
                if (!empty($rightAttribute['created'])) {
                    $pendingMagentoAttributes[$rightCode] = $leftAttribute;
                }
            } else {
                $rightAttribute = $rightCode !== ''
                    ? $this->magentoAttributeProvider->getAttribute($rightCode)
                    : null;
            }

            if ($rightCode !== '' && !$this->attributePolicy->isMappable($rightCode)) {
                throw new LocalizedException(__('Magento attribute "%1" is not available for mapping.', $rightCode));
            }
            if ($rightCode !== '' && !$rightAttribute) {
                throw new LocalizedException(__('Magento attribute "%1" is not available for mapping.', $rightCode));
            }

            $result[] = ['left' => $leftAttribute, 'right' => $rightAttribute];
        }

        return $result;
    }
}
