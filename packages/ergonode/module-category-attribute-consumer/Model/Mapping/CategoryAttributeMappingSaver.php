<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\Mapping;

use Ergonode\CategoryAttribute\Api\AttributeMappingWriterInterface;
use Ergonode\CategoryAttribute\Api\MagentoAttributeProviderInterface;
use Ergonode\CategoryAttributeConsumer\Model\Provider\ErgonodeCategoryAttributeProvider;
use Ergonode\CategoryAttributeConsumer\Model\Provider\MagentoCategoryAttributeCreator;
use Magento\Framework\Exception\LocalizedException;

class CategoryAttributeMappingSaver
{
    public function __construct(
        private readonly ErgonodeCategoryAttributeProvider $ergonodeAttributeProvider,
        private readonly MagentoAttributeProviderInterface $magentoAttributeProvider,
        private readonly MagentoCategoryAttributeCreator $magentoAttributeCreator,
        private readonly AttributeMappingWriterInterface $mappingWriter
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $mappings
     * @param array<int, array<string, mixed>> $visibility
     * @return array{inserted: int, updated: int, deleted: int, unchanged: int}
     */
    public function save(array $mappings, array $visibility): array
    {
        $resolved = [];
        $pendingAttributes = [];
        foreach ($mappings as $mapping) {
            $left = is_array($mapping['left'] ?? null) ? $mapping['left'] : null;
            $right = is_array($mapping['right'] ?? null) ? $mapping['right'] : null;
            $leftCode = trim((string)($left['code'] ?? ''));
            $rightCode = trim((string)($right['code'] ?? ''));
            if ($leftCode === '' && $rightCode === '') {
                continue;
            }

            $leftAttribute = $leftCode !== ''
                ? $this->ergonodeAttributeProvider->getAttribute($leftCode)
                : null;
            if ($leftCode !== '' && !$leftAttribute) {
                throw new LocalizedException(__('Ergonode category attribute "%1" is not available.', $leftCode));
            }

            if ($right && !empty($right['pending_create'])) {
                if (!$leftAttribute) {
                    throw new LocalizedException(
                        __('Choose an Ergonode category attribute before creating a Magento attribute.')
                    );
                }
                $rightAttribute = $this->magentoAttributeCreator->previewFromErgonodeAttribute($leftAttribute);
                $rightCode = (string)$rightAttribute['code'];
                if (!empty($rightAttribute['created'])) {
                    $pendingAttributes[$rightCode] = $leftAttribute;
                }
            } else {
                $rightAttribute = $rightCode !== ''
                    ? $this->magentoAttributeProvider->getAttribute($rightCode)
                    : null;
            }
            if ($rightCode !== '' && !$rightAttribute) {
                throw new LocalizedException(__('Magento category attribute "%1" is not available.', $rightCode));
            }

            $resolved[] = ['left' => $leftAttribute, 'right' => $rightAttribute];
        }
        $this->mappingWriter->validate($resolved);
        foreach ($pendingAttributes as $attribute) {
            $this->magentoAttributeCreator->createFromErgonodeAttribute($attribute);
        }

        return $this->mappingWriter->save($resolved, $visibility);
    }
}
