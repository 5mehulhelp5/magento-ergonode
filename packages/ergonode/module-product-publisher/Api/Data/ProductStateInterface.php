<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Api\Data;

interface ProductStateInterface
{
    public const string TYPE_SIMPLE = 'simple';
    public const string TYPE_VARIABLE = 'variable';
    public const string TYPE_GROUPING = 'grouping';

    /**
     * @return string
     */
    public function getSku(): string;

    /**
     * Magento product ID when the state originates from Magento.
     *
     * @return int|null
     */
    public function getMagentoProductId(): ?int;

    /**
     * Native Ergonode SKU, or null before Ergonode assigns it.
     *
     * @return string|null
     */
    public function getErgonodeSku(): ?string;

    /** @return string */
    public function getIdentityMode(): string;

    /**
     * @return string
     */
    public function getType(): string;

    /**
     * @return string
     */
    public function getTemplateCode(): string;

    /**
     * @return array<string, string>
     */
    public function getStatuses(): array;

    /**
     * @return ProductAttributeValueInterface[]
     */
    public function getValues(): array;

    /**
     * @return ProductRelationStateInterface
     */
    public function getRelations(): ProductRelationStateInterface;

    /**
     * @return bool
     */
    public function isDeleted(): bool;
}
