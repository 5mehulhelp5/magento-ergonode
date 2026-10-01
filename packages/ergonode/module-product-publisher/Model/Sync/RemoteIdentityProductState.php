<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\Sync;

use Ergonode\ProductPublisher\Api\Data\ProductCollectionCompletenessInterface;
use Ergonode\ProductPublisher\Api\Data\ProductCreationContextInterface;
use Ergonode\ProductPublisher\Api\Data\ProductRelationStateInterface;
use Ergonode\ProductPublisher\Api\Data\ProductStateDecoratorInterface;
use Ergonode\ProductPublisher\Api\Data\ProductStateInterface;

final readonly class RemoteIdentityProductState implements
    ProductCollectionCompletenessInterface,
    ProductCreationContextInterface,
    ProductStateDecoratorInterface
{
    /** @param \Ergonode\ProductPublisher\Api\Data\ProductAttributeValueInterface[] $values */
    public function __construct(
        private ProductStateInterface $source,
        private string $ergonodeSku,
        private array $values,
        private ProductRelationStateInterface $relations,
        private bool $createdInCurrentSynchronization = false
    ) {
    }

    public function getSku(): string
    {
        return $this->ergonodeSku;
    }

    public function getMagentoProductId(): ?int
    {
        return $this->source->getMagentoProductId();
    }

    public function getErgonodeSku(): ?string
    {
        return $this->ergonodeSku;
    }

    public function getIdentityMode(): string
    {
        return $this->source->getIdentityMode();
    }

    public function getType(): string
    {
        return $this->source->getType();
    }

    public function getTemplateCode(): string
    {
        return $this->source->getTemplateCode();
    }

    public function getStatuses(): array
    {
        return $this->source->getStatuses();
    }

    public function getValues(): array
    {
        return $this->values;
    }

    public function getRelations(): ProductRelationStateInterface
    {
        return $this->relations;
    }

    public function isDeleted(): bool
    {
        return $this->source->isDeleted();
    }

    public function wasCreatedInCurrentSynchronization(): bool
    {
        return $this->createdInCurrentSynchronization;
    }

    public function getDecoratedProductState(): ProductStateInterface
    {
        return $this->source;
    }

    public function isCollectionAuthoritative(string $collection, ?string $identifier = null): bool
    {
        return $this->source instanceof ProductCollectionCompletenessInterface
            && $this->source->isCollectionAuthoritative($collection, $identifier);
    }
}
