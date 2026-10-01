<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisher\Model\Publication;

use Ergonode\ProductAttributePublisher\Api\AttributeDefinitionPublisherInterface;
use Ergonode\AttributePublisher\Api\AttributeSynchronizerInterface;
use Ergonode\AttributePublisher\Api\AttributeCreateBatchSynchronizerInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeStateInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeSynchronizationResultInterface;
use Ergonode\Publisher\Model\GraphQl\SynchronizationRateLimitGuard;
use Magento\Framework\Exception\LocalizedException;
use Ergonode\AttributePublisher\Api\AttributeTypeResolverInterface;
use Ergonode\ProductAttributePublisher\Api\AttributeSourceStateBuilderInterface;
use Ergonode\ProductAttribute\Model\Provider\MagentoOptionProvider;

class AttributeDefinitionPublisher implements AttributeDefinitionPublisherInterface
{
    public function __construct(
        private readonly AttributeSynchronizerInterface $synchronizer,
        private readonly AttributeCreateBatchSynchronizerInterface $batchSynchronizer,
        private readonly AttributeSourceStateBuilderInterface $stateBuilder,
        private readonly MagentoOptionProvider $magentoOptionProvider,
        private readonly AttributeTypeResolverInterface $attributeTypeResolver,
        private readonly SynchronizationRateLimitGuard $rateLimitGuard
    ) {
    }

    /**
     * @param array<string, mixed> $source
     */
    public function prepareState(array $source): AttributeStateInterface
    {
        $magentoCode = trim((string)($source['code'] ?? ''));
        $targetType = strtolower(trim((string)($source['target_type'] ?? '')));
        $ergonodeType = $this->attributeTypeResolver->resolve($targetType) ?? $targetType;
        $optionIds = [];
        if (in_array($ergonodeType, ['select', 'multi_select'], true)) {
            $optionIds = $this->optionIds($magentoCode);
        }

        return $this->stateBuilder->build(
            $magentoCode,
            $magentoCode,
            $ergonodeType,
            $optionIds
        );
    }

    public function publish(AttributeStateInterface $state): AttributeSynchronizationResultInterface
    {
        $result = $this->synchronizer->synchronize($state, AttributeSynchronizerInterface::MODE_CREATE_ONLY);
        $this->rateLimitGuard->throwIfLimited($result);
        if (!$result->isSuccessful()) {
            throw new LocalizedException(__('Unable to publish Ergonode attribute "%1".', $state->getCode()));
        }

        return $result;
    }

    /**
     * @param  AttributeStateInterface[] $states
     * @return array<string, AttributeSynchronizationResultInterface>
     */
    public function publishBatch(array $states): array
    {
        $results = $this->batchSynchronizer->synchronizeBatch($states);
        foreach ($results as $result) {
            $this->rateLimitGuard->throwIfLimited($result);
        }

        return $results;
    }

    /**
     * @return int[]
     */
    private function optionIds(string $magentoCode): array
    {
        $result = [];
        foreach ($this->magentoOptionProvider->getOptions($magentoCode) as $option) {
            $code = trim((string)($option['code'] ?? ''));
            if (preg_match('/^option_(\d+)$/', $code, $matches)) {
                $result[] = (int)$matches[1];
            }
        }

        return $result;
    }
}
