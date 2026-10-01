<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisherAdminUi\Model;

use Ergonode\AttributePublisher\Api\AttributeSynchronizerInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeStateInterface;
use Ergonode\CategoryAttributePublisherAdminUi\Model\Mapping\SourceMetadata;
use Ergonode\CategoryAttributeAdminUi\Model\Mapping\MappingProvider;
use Ergonode\CategoryAttributePublisher\Api\CategoryAttributeRegistrySynchronizerInterface;
use Ergonode\CategoryAttributePublisher\Model\Provider\CategoryAttributeSourceStateBuilder;
use Ergonode\Publisher\Model\GraphQl\SynchronizationRateLimitGuard;
use Magento\Framework\Exception\LocalizedException;

class ErgonodeCategoryAttributeCreator
{
    public function __construct(
        private readonly CategoryAttributeSourceStateBuilder $stateBuilder,
        private readonly MappingProvider $mappingProvider,
        private readonly AttributeSynchronizerInterface $attributeSynchronizer,
        private readonly CategoryAttributeRegistrySynchronizerInterface $registrySynchronizer,
        private readonly SourceMetadata $sourceMetadata,
        private readonly SynchronizationRateLimitGuard $rateLimitGuard
    ) {
    }

    public function synchronizeFromMagento(string $attributeCode, string $targetType): AttributeStateInterface
    {
        $attributeCode = trim($attributeCode);
        if (!in_array($attributeCode, array_column($this->mappingProvider->getMagentoAttributes(), 'code'), true)) {
            throw new LocalizedException(__(
                'Magento category attribute "%1" is not available for Ergonode mapping.',
                $attributeCode
            ));
        }
        $state = $this->stateBuilder->build($attributeCode, $targetType);
        $result = $this->attributeSynchronizer->synchronize(
            $state,
            AttributeSynchronizerInterface::MODE_CREATE_ONLY
        );
        $this->rateLimitGuard->throwIfLimited($result);
        if (!$result->isSuccessful()) {
            throw new LocalizedException(__('Unable to create Ergonode attribute "%1".', $state->getCode()));
        }
        $this->registrySynchronizer->ensure($state->getCode());
        $this->sourceMetadata->reset();

        return $state;
    }
}
