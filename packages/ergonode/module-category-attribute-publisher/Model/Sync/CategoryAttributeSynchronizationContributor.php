<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisher\Model\Sync;

use Ergonode\CategoryAttributePublisher\Api\Data\CategoryAttributeStateInterface;
use Ergonode\CategoryAttributePublisher\Api\Data\CategoryAttributeValueInterface;
use Ergonode\CategoryAttributePublisher\Model\GraphQl\CategoryAttributeMutationFactory;
use Ergonode\CategoryPublisher\Api\CategorySynchronizationContributorInterface;
use Ergonode\CategoryPublisher\Api\CategoryBatchSynchronizationContributorInterface;
use Ergonode\CategoryPublisher\Api\CategorySynchronizerInterface;
use Ergonode\CategoryPublisher\Api\Data\CategoryStateInterface;
use Ergonode\Core\Api\Exception\GraphQlRequestException;
use Magento\Framework\Exception\LocalizedException;

class CategoryAttributeSynchronizationContributor implements CategoryBatchSynchronizationContributorInterface
{
    /** @var array<string, CategoryAttributeStateInterface> */
    private array $batchStates = [];

    public function __construct(
        private readonly CategoryAttributeStateLoader $loader,
        private readonly CategoryAttributeMutationFactory $mutations
    ) {
    }

    public function forBatch(array $states, string $mode): CategorySynchronizationContributorInterface
    {
        $scopes = [];
        foreach ($states as $code => $state) {
            $attributes = $this->attributeState($state);
            if ($attributes !== null && $mode !== CategorySynchronizerInterface::MODE_CREATE_STRICT) {
                $scopes[$code] = $mode === CategorySynchronizerInterface::MODE_RECONCILE
                    ? [] : $this->languages($attributes);
            }
        }
        $contributor = clone $this;
        $contributor->batchStates = [];
        try {
            $contributor->batchStates = $this->loader->loadBatch($scopes);
        } catch (GraphQlRequestException $exception) {
            throw $exception;
        } catch (LocalizedException) {
            // Preserve per-category failure isolation when batch data cannot be normalized.
            // Transport and rate-limit errors must never trigger individual re-reads.
            return $contributor;
        }
        return $contributor;
    }

    public function planNext(CategoryStateInterface $desiredState, string $mode): array
    {
        $desired = $this->attributeState($desiredState);
        if ($desired === null || $mode === CategorySynchronizerInterface::MODE_CREATE_STRICT) {
            return [];
        }
        $remote = $this->batchStates[$desiredState->getCode()] ?? $this->loader->load(
            $desiredState->getCode(),
            $mode === CategorySynchronizerInterface::MODE_RECONCILE ? [] : $this->languages($desired)
        );
        $desiredValues = $this->byCode($desired->getValues());
        $requiredAttributeCodes = array_values(array_unique([
            ...$desired->getAllowedAttributeCodes(),
            ...array_keys($desiredValues),
        ]));
        $allowedAdd = array_diff($requiredAttributeCodes, $remote->getAllowedAttributeCodes());
        if ($allowedAdd !== []) {
            return array_map($this->mutations->addAllowedAttribute(...), array_values($allowedAdd));
        }

        $remoteValues = $this->byCode($remote->getValues());
        $upserts = [];
        foreach ($desiredValues as $code => $value) {
            $remoteValue = $remoteValues[$code] ?? null;
            if ($remoteValue === null
                || $value->getType() !== $remoteValue->getType()
                || !$this->desiredTranslationsMatch($value, $remoteValue)
            ) {
                $upserts[] = $this->mutations->setValue($desiredState->getCode(), $value);
            }
        }
        if ($upserts !== []) {
            return $upserts;
        }
        if ($mode !== CategorySynchronizerInterface::MODE_RECONCILE) {
            return [];
        }

        $translationDeletes = [];
        foreach ($remoteValues as $code => $remoteValue) {
            $desiredLanguages = isset($desiredValues[$code])
                ? array_keys($desiredValues[$code]->getTranslations())
                : [];
            $languages = array_diff(array_keys($remoteValue->getTranslations()), $desiredLanguages);
            if ($languages !== []) {
                $translationDeletes[] = $this->mutations->deleteValueTranslations(
                    $desiredState->getCode(),
                    $code,
                    array_values($languages)
                );
            }
        }

        return $translationDeletes;
    }

    private function attributeState(CategoryStateInterface $state): ?CategoryAttributeStateInterface
    {
        foreach ($state->getContributions() as $contribution) {
            if ($contribution instanceof CategoryAttributeStateInterface) {
                return $contribution;
            }
        }

        return null;
    }

    /** @param CategoryAttributeValueInterface[] $values @return array<string, CategoryAttributeValueInterface> */
    private function byCode(array $values): array
    {
        $result = [];
        foreach ($values as $value) {
            $result[$value->getAttributeCode()] = $value;
        }

        return $result;
    }

    /** @return string[] */
    private function languages(CategoryAttributeStateInterface $state): array
    {
        $languages = [];
        foreach ($state->getValues() as $value) {
            $languages = [...$languages, ...array_keys($value->getTranslations())];
        }

        return array_values(array_unique($languages));
    }

    private function desiredTranslationsMatch(
        CategoryAttributeValueInterface $desired,
        CategoryAttributeValueInterface $remote
    ): bool {
        foreach ($desired->getTranslations() as $language => $value) {
            if (!array_key_exists($language, $remote->getTranslations())
                || $remote->getTranslations()[$language] !== $value
            ) {
                return false;
            }
        }

        return true;
    }
}
