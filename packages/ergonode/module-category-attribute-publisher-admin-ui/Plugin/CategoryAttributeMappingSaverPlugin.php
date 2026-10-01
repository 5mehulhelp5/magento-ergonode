<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisherAdminUi\Plugin;

use Ergonode\CategoryAttributeAdminUi\Model\Mapping\AttributeMappingSaver;
use Ergonode\CategoryAttributePublisherAdminUi\Model\ErgonodeCategoryAttributeCreator;
use Magento\Framework\Exception\LocalizedException;

class CategoryAttributeMappingSaverPlugin
{
    public function __construct(
        private readonly ErgonodeCategoryAttributeCreator $attributeCreator
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $mappings
     * @return array{array<int, array<string, mixed>>}
     */
    public function beforePrepareMappings(
        AttributeMappingSaver $subject,
        array $mappings
    ): array {
        foreach ($mappings as &$mapping) {
            $left = is_array($mapping['left'] ?? null) ? $mapping['left'] : null;
            $right = is_array($mapping['right'] ?? null) ? $mapping['right'] : null;
            if (!$left || empty($left['pending_create'])) {
                continue;
            }
            $attributeCode = trim((string)($right['code'] ?? ''));
            if ($attributeCode === '') {
                throw new LocalizedException(__(
                    'Choose a Magento category attribute before creating an Ergonode attribute.'
                ));
            }
            $this->attributeCreator->synchronizeFromMagento(
                $attributeCode,
                trim((string)($left['type'] ?? ''))
            );
            $mapping['left'] = ['code' => $attributeCode];
        }
        unset($mapping);

        return [$mappings];
    }
}
