<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\Sync;

use Ergonode\ProductPublisher\Api\Data\ProductAttributeValueClearIntentInterface;
use Ergonode\ProductPublisher\Api\Data\ProductStateInterface;
use Ergonode\ProductPublisher\Api\Data\ProductCreationContextInterface;
use Ergonode\ProductPublisher\Model\GraphQl\RemoteProductPublicationStateLoader;
use Ergonode\ProductPublisher\Model\GraphQl\ProductMutationFactory;
use Ergonode\Publisher\Api\Data\MutationOperationInterface;
use Ergonode\Publisher\Api\Data\MutationResultInterface;
use Magento\Framework\Exception\LocalizedException;

class ProductPublicationMutationPlanner
{
    public function __construct(
        private readonly ProductMutationFactory $mutations,
        private readonly RemoteProductPublicationStateLoader $remoteState
    ) {
    }

    public function create(ProductStateInterface $state): MutationOperationInterface
    {
        return $this->mutations->create($state);
    }

    public function delete(ProductStateInterface $state): MutationOperationInterface
    {
        return $this->mutations->delete($state->getSku());
    }

    /**
     * @param array<string, ProductStateInterface> $states
     * @param array<string, true> $updateBase
     * @return MutationOperationInterface[]
     */
    public function operations(array $states, array $updateBase, bool $compareRemoteState = true): array
    {
        $unchanged = $compareRemoteState ? $this->unchangedTemplates($states, $updateBase) : [];
        $operations = [];
        foreach ($states as $stateKey => $state) {
            if ($state->isDeleted()) {
                continue;
            }
            $template = [];
            $statuses = [];
            $values = [];
            $bindings = [];
            $variants = [];
            $groupedChildren = [];
            if (isset($updateBase[$stateKey]) && !isset($unchanged['sku:' . $state->getSku()])) {
                $template[] = $this->mutations->setTemplate($state->getSku(), $state->getTemplateCode());
            }
            foreach ($state->getStatuses() as $language => $status) {
                $statuses[] = $this->mutations->setStatus($state->getSku(), $language, $status);
            }
            foreach ($state->getValues() as $value) {
                foreach (array_keys($value->getTranslations()) as $language) {
                    $values[] = $this->mutations->setValue($state->getSku(), $value, $language);
                }
                if ($value instanceof ProductAttributeValueClearIntentInterface) {
                    foreach ($value->getClearedLanguageCodes() as $language) {
                        $key = 'sku:' . $state->getSku();
                        if (isset($unchanged[$key])
                            && !isset($unchanged[$key][$value->getAttributeCode()][$language])
                        ) {
                            continue;
                        }
                        $values[] = $this->mutations->deleteValueTranslation(
                            $state->getSku(),
                            $value->getAttributeCode(),
                            $language
                        );
                    }
                }
            }
            if ($state->getType() === ProductStateInterface::TYPE_VARIABLE) {
                $bindings[] = $this->mutations->setBindings(
                    $state->getSku(),
                    $state->getRelations()->getBindingCodes()
                );
                foreach ($state->getRelations()->getVariantSkus() as $variantSku) {
                    $variants[] = $this->mutations->addVariant($state->getSku(), $variantSku);
                }
            }
            if ($state->getType() === ProductStateInterface::TYPE_GROUPING) {
                foreach ($state->getRelations()->getGroupedChildren() as $childSku => $quantity) {
                    $groupedChildren[] = $this->mutations->addGroupedChild(
                        $state->getSku(),
                        $childSku,
                        $quantity
                    );
                }
            }
            $operations = [...$operations, ...$template, ...$statuses, ...$values,
                ...$bindings, ...$variants, ...$groupedChildren];
        }

        return $operations;
    }

    /**
     * Refine the validated plan only after confirmed creation and durable identity binding.
     *
     * @param MutationOperationInterface[] $operations
     * @param array<string, ProductStateInterface> $createdStates
     * @return MutationOperationInterface[]
     */
    public function afterCreation(array $operations, array $createdStates): array
    {
        if ($createdStates === []) {
            return $operations;
        }
        return $this->omitAbsentClears($operations, $this->matchingTemplates($createdStates));
    }

    /**
     * @param MutationOperationInterface[] $operations
     * @param array<string, array<string, array<string, true>>> $unchanged
     * @return MutationOperationInterface[]
     */
    private function omitAbsentClears(array $operations, array $unchanged): array
    {
        return array_values(array_filter($operations, static function (MutationOperationInterface $operation) use (
            $unchanged
        ): bool {
            $metadata = $operation->getMetadata();
            $key = 'sku:' . ($metadata['entity_sku'] ?? '');
            return $operation->getField() !== 'productDeleteAttributeValueTranslations'
                || !isset($unchanged[$key])
                || isset($unchanged[$key][$metadata['attribute_code'] ?? ''][$metadata['language'] ?? '']);
        }));
    }

    /**
     * @param array<string, ProductStateInterface> $states
     * @param array<string, true> $updateBase
     * @return array<string, array<string, array<string, true>>>
     */
    private function unchangedTemplates(array $states, array $updateBase): array
    {
        $candidates = array_filter(
            array_intersect_key($states, $updateBase),
            static fn (ProductStateInterface $state): bool => !$state->isDeleted()
                && !($state instanceof ProductCreationContextInterface && $state->wasCreatedInCurrentSynchronization())
        );
        return $this->matchingTemplates($candidates);
    }

    /**
     * @param array<string, ProductStateInterface> $candidates
     * @return array<string, array<string, array<string, true>>>
     */
    private function matchingTemplates(array $candidates): array
    {
        $remote = $this->remoteState->load(array_values(array_map(
            static fn (ProductStateInterface $state): string => $state->getSku(),
            $candidates
        )));
        $unchanged = [];
        foreach ($candidates as $state) {
            $current = $remote['sku:' . $state->getSku()] ?? null;
            if ($current !== null && $current['template'] === $state->getTemplateCode()) {
                $unchanged['sku:' . $state->getSku()] = $current['translations'];
            }
        }
        return $unchanged;
    }

    /**
     * @param array<string, ProductStateInterface> $states
     * @param array<string, true> $stateKeys
     * @return MutationOperationInterface[]
     */
    public function baseOperations(array $states, array $stateKeys): array
    {
        $operations = [];
        foreach ($states as $stateKey => $state) {
            if (!isset($stateKeys[$stateKey]) || $state->isDeleted()) {
                continue;
            }
            $operations[] = $this->mutations->setTemplate($state->getSku(), $state->getTemplateCode());
        }

        return $operations;
    }

    public function groupedQuantityFallback(MutationResultInterface $result): MutationOperationInterface
    {
        $input = $result->getOperation()->getVariables()['input'] ?? null;
        $value = $input?->getValue();
        if (!is_array($value)
            || !is_string($value['sku'] ?? null)
            || !is_string($value['childSku'] ?? null)
            || !is_int($value['quantity'] ?? null)
        ) {
            throw new LocalizedException(__('Grouped product mutation is missing fallback input metadata.'));
        }

        return $this->mutations->setGroupedChildQuantity(
            $value['sku'],
            $value['childSku'],
            $value['quantity']
        );
    }
}
