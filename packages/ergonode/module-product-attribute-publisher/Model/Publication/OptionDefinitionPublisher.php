<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisher\Model\Publication;

use Ergonode\ProductAttributePublisher\Api\OptionDefinitionPublisherInterface;
use Ergonode\AttributePublisher\Api\AttributeOptionSynchronizerInterface;
use Ergonode\AttributePublisher\Api\AttributeOptionBatchSynchronizerInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeOptionStateInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeOptionSynchronizationResultInterface;
use Ergonode\Publisher\Api\Data\SynchronizationResultInterface;
use Ergonode\Publisher\Model\GraphQl\SynchronizationRateLimitGuard;
use Magento\Framework\Exception\LocalizedException;
use Ergonode\ProductAttributePublisher\Model\Source\OptionSourceStateBuilder;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;

class OptionDefinitionPublisher implements OptionDefinitionPublisherInterface
{
    public function __construct(
        private readonly AttributeOptionSynchronizerInterface $synchronizer,
        private readonly AttributeOptionBatchSynchronizerInterface $batchSynchronizer,
        private readonly ProductAttributeRepositoryInterface $attributeRepository,
        private readonly OptionSourceStateBuilder $optionStateBuilder,
        private readonly SynchronizationRateLimitGuard $rateLimitGuard
    ) {
    }

    /**
     * @param array<string, mixed> $source
     */
    public function prepareState(
        string $magentoAttributeCode,
        string $ergonodeAttributeCode,
        array $source
    ): AttributeOptionStateInterface {
        $code = trim((string)($source['code'] ?? ''));
        if (!preg_match('/^option_(\d+)$/', $code, $matches)) {
            throw new LocalizedException(__('A Magento option identifier is required.'));
        }
        $attribute = $this->attributeRepository->get($magentoAttributeCode);

        return $this->optionStateBuilder->build($attribute, $ergonodeAttributeCode, [(int)$matches[1]])[0];
    }

    public function publish(string $attributeCode, AttributeOptionStateInterface $state): SynchronizationResultInterface
    {
        $result = $this->synchronizer->synchronize($attributeCode, $state);
        $this->rateLimitGuard->throwIfLimited($result);
        if (!$result->isSuccessful()) {
            throw new LocalizedException(__('Unable to publish Ergonode option "%1".', $state->getCode()));
        }

        return $result;
    }

    /**
     * @param  AttributeOptionStateInterface[] $states
     * @return array<string, AttributeOptionSynchronizationResultInterface>
     */
    public function publishBatch(string $attributeCode, array $states): array
    {
        $results = $this->batchSynchronizer->synchronizeBatch($attributeCode, $states);
        foreach ($results as $result) {
            $this->rateLimitGuard->throwIfLimited($result);
        }

        return $results;
    }
}
