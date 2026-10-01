<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryPublisher\Model\Data;

use Ergonode\ProductCategoryPublisher\Api\Data\ProductCategoryStateInterface;
use Ergonode\ProductPublisher\Api\Data\ProductCollectionCompletenessInterface;
use Ergonode\ProductPublisher\Api\Data\ProductRelationStateInterface;
use Ergonode\ProductPublisher\Api\Data\ProductStateDecoratorInterface;
use Ergonode\ProductPublisher\Api\Data\ProductStateInterface;
use InvalidArgumentException;

final readonly class ProductCategoryState implements
    ProductCollectionCompletenessInterface,
    ProductCategoryStateInterface,
    ProductStateDecoratorInterface
{
    /** @var string[] */
    private array $categoryCodes;

    /** @param string[] $categoryCodes */
    public function __construct(
        private ProductStateInterface $productState,
        array $categoryCodes,
        private bool $categorySourceAuthoritative
    ) {
        foreach ($categoryCodes as $categoryCode) {
            if (!is_string($categoryCode) || trim($categoryCode) === '') {
                throw new InvalidArgumentException('Product category codes cannot be empty.');
            }
        }
        $categoryCodes = array_values(array_unique(array_map('trim', $categoryCodes)));
        sort($categoryCodes);
        $this->categoryCodes = $categoryCodes;
    }

    public function getSku(): string
    {
        return $this->productState->getSku();
    }

    public function getMagentoProductId(): ?int
    {
        return $this->productState->getMagentoProductId();
    }

    public function getErgonodeSku(): ?string
    {
        return $this->productState->getErgonodeSku();
    }

    public function getIdentityMode(): string
    {
        return $this->productState->getIdentityMode();
    }

    public function getType(): string
    {
        return $this->productState->getType();
    }

    public function getTemplateCode(): string
    {
        return $this->productState->getTemplateCode();
    }

    public function getCategoryCodes(): array
    {
        return $this->categoryCodes;
    }

    public function getStatuses(): array
    {
        return $this->productState->getStatuses();
    }

    public function getValues(): array
    {
        return $this->productState->getValues();
    }

    public function getRelations(): ProductRelationStateInterface
    {
        return $this->productState->getRelations();
    }

    public function isDeleted(): bool
    {
        return $this->productState->isDeleted();
    }

    public function isCategorySourceAuthoritative(): bool
    {
        return $this->categorySourceAuthoritative;
    }

    public function getDecoratedProductState(): ProductStateInterface
    {
        return $this->productState;
    }

    public function isCollectionAuthoritative(string $collection, ?string $identifier = null): bool
    {
        if ($collection === ProductCategoryStateInterface::COLLECTION_CATEGORIES) {
            return $this->categorySourceAuthoritative;
        }

        return $this->productState instanceof ProductCollectionCompletenessInterface
            && $this->productState->isCollectionAuthoritative($collection, $identifier);
    }
}
