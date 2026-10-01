<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\Source;

use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Ergonode\Product\Api\ProductIdentityModeProviderInterface;
use Ergonode\ProductPublisher\Api\Data\ProductAttributeValueInterface;
use Ergonode\ProductPublisher\Api\Data\ProductCollectionCompletenessInterface;
use Ergonode\ProductPublisher\Api\ProductAttributePublicationSourceInterface;
use Ergonode\ProductPublisher\Api\ProductDesiredStateFactoryInterface;
use Ergonode\ProductPublisher\Api\ProductIdentityRegistryInterface;
use Ergonode\ProductPublisher\Api\ProductRelationSourcePoolInterface;
use Ergonode\ProductPublisher\Api\ProductSourceDecoratorInterface;
use Ergonode\ProductPublisher\Api\ProductTypeMapperInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\LocalizedException;

class ProductSourceStateBuilder
{
    public function __construct(
        private readonly ProductDesiredStateFactoryInterface $stateFactory,
        private readonly ProductTypeMapperInterface $typeMapper,
        private readonly ProductAttributePublicationSourceInterface $attributeSource,
        private readonly ProductRelationSourcePoolInterface $relationSourcePool,
        private readonly ProductIdentityRegistryInterface $identityRegistry,
        private readonly ProductIdentityModeProviderInterface $identityModeProvider,
        /** @var ProductSourceDecoratorInterface[] */
        private readonly array $decorators = []
    ) {
    }

    /** @param string[] $selectedSkus */
    public function build(ProductSourceData $source, array $selectedSkus = []): ProductSourceResult
    {
        if ($source->getProducts() === []) {
            return new ProductSourceResult([], true, []);
        }
        $targetCodesByMagentoCode = [];
        foreach ($source->getAttributeMappings() as $mapping) {
            if (!isset($mapping['publication_error'])) {
                $targetCodesByMagentoCode[$mapping['magento_attribute_code']] = $mapping['ergonode_attribute_code'];
            }
        }
        $states = [];
        $availableSkus = [];
        $skippedProductMessages = [];
        $skippedProductWarnings = [];
        $productWarnings = [];
        $authoritative = true;
        $selected = array_fill_keys($selectedSkus, true);
        $fullScope = $selectedSkus === [];
        $productIds = array_values(array_map(
            static fn (Product $product): int => (int)$product->getId(),
            $source->getProducts()
        ));
        $identities = $this->identityRegistry->getIdentitiesByProductIds($productIds);
        $identityValues = [];
        $identityOwners = [];
        $identityError = null;
        if ($this->identityModeProvider->getMode() === ProductIdentityInterface::MODE_MAPPED
            || array_filter($identities, static fn (ProductIdentityInterface $identity): bool =>
                $identity->getIdentityMode() === ProductIdentityInterface::MODE_MAPPED) !== []
        ) {
            try {
                $identityValues = $this->identityRegistry->getMappedSkuValuesByProductIds($productIds);
                $identityOwners = $this->identityRegistry->findMappedSkuProductIds(array_values($identityValues));
            } catch (LocalizedException $exception) {
                $identityError = $exception;
            }
        }
        foreach ($source->getProducts() as $sku => $product) {
            $sku = (string)$sku;
            $identity = $identities[(int)$product->getId()] ?? null;
            if ($identityError !== null
                && $this->identityModeProvider->getMode() === ProductIdentityInterface::MODE_MAPPED
            ) {
                if ($fullScope || isset($selected[$sku])) {
                    $authoritative = false;
                    $skippedProductWarnings[$sku] = $identityError->getMessage();
                }
                continue;
            }
            if ($identity?->getIdentityMode() === ProductIdentityInterface::MODE_SHARED
                && $identity->getErgonodeSku() !== $sku
            ) {
                if ($fullScope || isset($selected[$sku])) {
                    $authoritative = false;
                    $skippedProductWarnings[$sku] = (string)__(
                        'Magento product ID %1 was published as immutable Ergonode SKU "%2" and now uses SKU "%3". '
                        . 'Resolve the identity mapping before publishing it again.',
                        (int)$product->getId(),
                        $identity->getErgonodeSku(),
                        $sku
                    );
                }
                continue;
            }
            try {
                $type = $this->typeMapper->map((string)$product->getTypeId());
            } catch (LocalizedException $exception) {
                if ($fullScope || isset($selected[$sku])) {
                    $authoritative = false;
                    $skippedProductWarnings[$sku] = $exception->getMessage();
                }
                continue;
            }
            $templateCode = $source->getTemplateCodes()[(int)$product->getAttributeSetId()] ?? null;
            if ($templateCode === null) {
                if ($fullScope || isset($selected[$sku])) {
                    $authoritative = false;
                    $skippedProductWarnings[$sku] = (string)__(
                        'Skipped Magento product "%1" because attribute set ID %2 has no Ergonode template mapping.',
                        $sku,
                        (int)$product->getAttributeSetId()
                    );
                }
                continue;
            }
            $values = [];
            $attributeCodesForSet = $source->getAttributeCodesForSet((int)$product->getAttributeSetId());
            $completeness = [];
            try {
                $mappedSku = '';
                $identityMode = $identity?->getIdentityMode() ?? $this->identityModeProvider->getMode();
                if ($identity === null) {
                    $this->identityModeProvider->assertNewModeAvailable($identityMode);
                } else {
                    $this->identityModeProvider->assertModeAvailable($identityMode);
                }
                if ($identityMode === ProductIdentityInterface::MODE_MAPPED) {
                    $mappedSku = $this->mappedSku(
                        (int)$product->getId(),
                        $sku,
                        $identity,
                        $identityValues,
                        $identityOwners,
                        $identityError
                    );
                } elseif ($identity !== null) {
                    $this->assertHistoricalIdentityCompatible(
                        (int)$product->getId(),
                        $sku,
                        $identity,
                        $identityValues
                    );
                }
                $mappings = array_filter(
                    $source->getAttributeMappings(),
                    static fn (array $mapping): bool => in_array(
                        $mapping['ergonode_attribute_code'],
                        $attributeCodesForSet,
                        true
                    )
                );
                $attributeResult = $this->attributeSource->getValues(
                    $product,
                    $source->getStoreProducts(),
                    $mappings
                );
                $values = $attributeResult->getValues();
                if ($identityMode === ProductIdentityInterface::MODE_ASSIGNED) {
                    $this->assertAssignedSkuValue($sku, $templateCode, $mappings, $values);
                }
                $warnings = $attributeResult->getWarnings();
                foreach ($values as $value) {
                    $completeness[
                        ProductCollectionCompletenessInterface::COLLECTION_VALUES . ':' . $value->getAttributeCode()
                    ] = true;
                }
            } catch (LocalizedException $exception) {
                if ($fullScope || isset($selected[$sku])) {
                    $authoritative = false;
                    $skippedProductWarnings[$sku] = $exception->getMessage();
                }
                continue;
            }
            $relationSource = $this->relationSourcePool->extract($product, array_filter(
                $targetCodesByMagentoCode,
                static fn (string $code): bool => in_array($code, $attributeCodesForSet, true)
            ));
            $ergonodeSku = $identity?->getErgonodeSku()
                ?? match ($identityMode) {
                    ProductIdentityInterface::MODE_SHARED => $sku,
                    ProductIdentityInterface::MODE_MAPPED => $mappedSku,
                    default => null,
                };
            $states[] = $this->stateFactory->createProduct(
                $sku,
                $type,
                $templateCode,
                [],
                $values,
                $relationSource->getRelations(),
                false,
                $completeness + $relationSource->getAuthoritativeCollections(),
                (int)$product->getId() > 0 ? (int)$product->getId() : null,
                $ergonodeSku,
                $identityMode
            );
            $availableSkus[$sku] = true;
            if ($warnings !== []) {
                $productWarnings[$sku] = array_values(array_unique($warnings));
            }
        }
        foreach ($selectedSkus as $sku) {
            if (!isset($availableSkus[$sku])) {
                $authoritative = false;
            }
        }

        foreach ($this->decorators as $decorator) {
            if (!$decorator instanceof ProductSourceDecoratorInterface) {
                throw new LocalizedException(__('Product source decorator must implement the public contract.'));
            }
            $states = $decorator->decorate($states, $source->getProducts());
        }

        return new ProductSourceResult(
            $states,
            $authoritative,
            $skippedProductMessages,
            $productWarnings,
            $skippedProductWarnings
        );
    }

    /** @param array<int, string> $identityValues */
    private function assertHistoricalIdentityCompatible(
        int $productId,
        string $magentoSku,
        ProductIdentityInterface $identity,
        array $identityValues
    ): void {
        $value = trim((string)($identityValues[$productId] ?? ''));
        if ($this->identityModeProvider->getMode() !== ProductIdentityInterface::MODE_MAPPED
            || $value === '' || $value === $identity->getErgonodeSku()
        ) {
            return;
        }
        throw new LocalizedException(__(
            'Magento product "%1" retains a historical "%2" binding to Ergonode SKU "%3", '
                . 'but its configured identity attribute contains "%4". Reconcile or migrate the '
                . 'binding explicitly before publication.',
            $magentoSku,
            $identity->getIdentityMode(),
            $identity->getErgonodeSku(),
            $value
        ));
    }

    /** @param array<int, string> $identityValues @param array<string, int> $identityOwners */
    private function mappedSku(
        int $productId,
        string $magentoSku,
        ?ProductIdentityInterface $identity,
        array $identityValues,
        array $identityOwners,
        ?LocalizedException $identityError
    ): string {
        if ($identityError !== null) {
            throw $identityError;
        }
        $mappedSku = trim((string)($identityValues[$productId] ?? ''));
        if ($mappedSku === '' || strlen($mappedSku) > 64) {
            throw new LocalizedException(__(
                'Magento product "%1" needs a non-empty Ergonode SKU of at most 64 bytes '
                    . 'in attribute "%2".',
                $magentoSku,
                $this->identityRegistry->getMappedSkuAttributeCode()
            ));
        }
        if (($identityOwners[$mappedSku] ?? null) !== $productId) {
            throw new LocalizedException(__(
                'Magento product "%1" does not uniquely own Ergonode SKU "%2" in attribute "%3".',
                $magentoSku,
                $mappedSku,
                $this->identityRegistry->getMappedSkuAttributeCode()
            ));
        }
        if ($identity !== null && $identity->getErgonodeSku() !== $mappedSku) {
            throw new LocalizedException(__(
                'Magento product "%1" has Ergonode SKU "%2" in its identity attribute '
                    . 'but is bound to "%3".',
                $magentoSku,
                $mappedSku,
                $identity->getErgonodeSku()
            ));
        }

        return $mappedSku;
    }

    /**
     * @param array<array<string, mixed>> $mappings
     * @param ProductAttributeValueInterface[] $values
     */
    private function assertAssignedSkuValue(
        string $magentoSku,
        string $templateCode,
        array $mappings,
        array $values
    ): void {
        $skuMappings = array_values(array_filter(
            $mappings,
            static fn (array $mapping): bool => ($mapping['magento_attribute_code'] ?? null) === 'sku'
                && !isset($mapping['publication_error'])
        ));
        if (count($skuMappings) !== 1) {
            throw new LocalizedException(__(
                'Assigned Ergonode SKU requires a publishable Magento sku mapping in template "%1".',
                $templateCode
            ));
        }
        $targetCode = (string)$skuMappings[0]['ergonode_attribute_code'];
        foreach ($values as $value) {
            if ($value->getAttributeCode() !== $targetCode) {
                continue;
            }
            $translations = $value->getTranslations();
            if ($translations !== [] && array_filter(
                $translations,
                static fn ($translation): bool => $translation !== $magentoSku
            ) === []) {
                return;
            }
        }
        throw new LocalizedException(__(
            'Magento sku "%1" has no matching publishable value in Ergonode attribute "%2".',
            $magentoSku,
            $targetCode
        ));
    }
}
