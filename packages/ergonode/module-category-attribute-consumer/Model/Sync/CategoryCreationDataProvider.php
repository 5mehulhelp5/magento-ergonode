<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\Sync;

use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryAttributeSourcePreparation;

use Ergonode\CategoryAttributeConsumer\Model\Import\CategoryEntityLoader;
use Ergonode\CategoryConsumer\Api\CategoryCreationDataProviderInterface;
use Magento\Framework\Exception\LocalizedException;

class CategoryCreationDataProvider implements CategoryCreationDataProviderInterface
{
    public function __construct(
        private readonly CategoryAttributeSourcePreparation $attributePreparation,
        private readonly CategoryCreationConfigurationProvider $configurationProvider,
        private readonly CategoryEntityLoader $entityLoader,
        private readonly CategoryAttributeValueMapper $valueMapper,
        private readonly CategoryDataWorkProvider $workProvider
    ) {
    }

    /**
     * @return array{
     *     values: array{is_active: int, include_in_menu: int},
     *     entity: array{
     *         code: string,
     *         labels: array<string, string>,
     *         attributes: array<int, array<string, mixed>>,
     *         hash: string,
     *         raw: array<string, mixed>
     *     }|null
     * }
     * @throws LocalizedException
     */
    public function get(string $categoryCode): array
    {
        $this->attributePreparation->ensurePrepared();
        $configuration = $this->configurationProvider->get();
        $values = $configuration['fixed_values'];
        $mappedCodes = $configuration['mapped_attribute_codes'];
        if (!$configuration['attributes_enabled'] || ($mappedCodes === [] && !$this->workProvider->hasWork())) {
            return ['values' => $values, 'entity' => null];
        }

        $entity = $this->entityLoader->load($categoryCode);
        if ($entity === null) {
            throw new LocalizedException(__('Ergonode category "%1" no longer exists.', $categoryCode));
        }
        $mappedValues = $mappedCodes === []
            ? []
            : $this->valueMapper->map($entity['attributes'], $mappedCodes);
        foreach ($mappedCodes as $attributeCode) {
            if (!array_key_exists(0, $mappedValues[$attributeCode] ?? [])) {
                throw new LocalizedException(__(
                    'Category "%1" has no mapped admin value for required Magento attribute "%2".',
                    $categoryCode,
                    $attributeCode
                ));
            }
            $values[$attributeCode] = (int)(bool)$mappedValues[$attributeCode][0];
        }

        return ['values' => $values, 'entity' => $entity];
    }
}
