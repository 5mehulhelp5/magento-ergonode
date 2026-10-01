<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Model\Import;

use Ergonode\AttributeConsumer\Api\AttributeCacheRefresherInterface;
use Ergonode\AttributeConsumer\Api\AttributeDefinitionSynchronizationInterface;
use Ergonode\AttributeConsumer\Api\WriteScopeAttributeCacheRefresherInterface;
use Ergonode\Core\Api\PaginatedImporterRefresherInterface;
use Magento\Framework\Exception\LocalizedException;

class AttributeCacheRefresher implements
    AttributeCacheRefresherInterface,
    WriteScopeAttributeCacheRefresherInterface
{
    public function __construct(
        private readonly AttributeDefinitionSynchronizationInterface $definitionSynchronization,
        private readonly OptionBatchImporter $optionBatchImporter,
        private readonly PaginatedImporterRefresherInterface $paginatedRefresher,
        private readonly OptionCacheReconciler $optionCacheReconciler
    ) {
    }

    public function refreshAttributes(): void
    {
        $this->refreshAttributesWithScope(false);
    }

    public function refreshAttributesWriteScope(): void
    {
        $this->refreshAttributesWithScope(true);
    }

    public function refreshOptions(string $attributeCode): void
    {
        $this->refreshOptionsWithScope($attributeCode, false);
    }

    public function refreshOptionsWriteScope(string $attributeCode): void
    {
        $this->refreshOptionsWithScope($attributeCode, true);
    }

    private function refreshAttributesWithScope(bool $writeScope): void
    {
        $this->definitionSynchronization->synchronize(true, $writeScope);
    }

    private function refreshOptionsWithScope(string $attributeCode, bool $writeScope): void
    {
        $positionOffset = 0;
        $optionCodes = [];
        $this->paginatedRefresher->refresh(
            function (?string $cursor) use (
                $attributeCode,
                $writeScope,
                &$positionOffset,
                &$optionCodes
            ): array {
                $result = $writeScope
                    ? $this->optionBatchImporter->importWriteScope(
                        $attributeCode,
                        $cursor,
                        null,
                        $positionOffset
                    )
                    : $this->optionBatchImporter->import(
                        $attributeCode,
                        $cursor,
                        null,
                        $positionOffset
                    );
                $positionOffset += (int)($result['processed'] ?? 0);
                foreach ($result['option_codes'] as $code) {
                    if (isset($optionCodes[$code])) {
                        throw new LocalizedException(
                            __('Ergonode returned a duplicate option across snapshot pages.')
                        );
                    }
                    $optionCodes[$code] = true;
                }

                return $result;
            }
        );
        $this->optionCacheReconciler->reconcile($attributeCode, array_map('strval', array_keys($optionCodes)));
    }
}
