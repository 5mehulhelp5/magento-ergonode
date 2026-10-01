<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\Data;

use Ergonode\ProductPublisher\Api\Data\ProductAttributeValueInterface;
use Ergonode\ProductPublisher\Api\Data\ProductCollectionCompletenessInterface;
use Ergonode\ProductPublisher\Api\Data\ProductRelationStateInterface;
use Ergonode\ProductPublisher\Api\Data\ProductStateInterface;
use Ergonode\Product\Api\Data\ProductIdentityInterface;
use InvalidArgumentException;

final readonly class ProductState implements ProductCollectionCompletenessInterface
{
    private string $sku;

    private string $type;

    private string $templateCode;

    private bool $deleted;

    private ?int $magentoProductId;

    private ?string $ergonodeSku;

    private string $identityMode;

    /** @var array<string, string> */
    private array $statuses;

    /** @var ProductAttributeValueInterface[] */
    private array $values;

    /** @var array<string, bool> */
    private array $authoritativeCollections;

    /**
     * Magento type mapping: simple -> simple, configurable -> variable,
     * grouped -> grouping. Callers pass the normalized target type.
     *
     * @param array<string, string> $statuses
     * @param ProductAttributeValueInterface[] $values
     * @param array<string, bool> $authoritativeCollections
     */
    public function __construct(
        string $sku,
        string $type,
        string $templateCode,
        array $statuses = [],
        array $values = [],
        ?ProductRelationStateInterface $relations = null,
        bool $deleted = false,
        array $authoritativeCollections = [],
        ?int $magentoProductId = null,
        ?string $ergonodeSku = null,
        string $identityMode = ProductIdentityInterface::MODE_SHARED
    ) {
        $this->sku = trim($sku);
        $this->type = $type;
        $this->templateCode = trim($templateCode);
        $this->deleted = $deleted;
        $this->magentoProductId = $magentoProductId;
        $this->identityMode = $identityMode;
        $this->ergonodeSku = $ergonodeSku !== null
            ? trim($ergonodeSku)
            : ($this->identityMode === ProductIdentityInterface::MODE_SHARED ? $this->sku : null);
        if ($this->sku === '') {
            throw new InvalidArgumentException('Product SKU cannot be empty.');
        }
        if (($this->magentoProductId !== null && $this->magentoProductId < 1)
            || ($this->ergonodeSku !== null && $this->ergonodeSku === '')
            || !in_array($this->identityMode, [
                ProductIdentityInterface::MODE_SHARED,
                ProductIdentityInterface::MODE_ASSIGNED,
                ProductIdentityInterface::MODE_MAPPED,
            ], true)
        ) {
            throw new InvalidArgumentException('Invalid product identity context.');
        }
        if (!in_array($this->type, [self::TYPE_SIMPLE, self::TYPE_VARIABLE, self::TYPE_GROUPING], true)) {
            throw new InvalidArgumentException('Unsupported product type: ' . $this->type);
        }
        if (!$this->deleted && $this->templateCode === '') {
            throw new InvalidArgumentException('Product template code cannot be empty.');
        }
        $normalizedStatuses = [];
        foreach ($statuses as $language => $status) {
            $language = trim((string)$language);
            $status = trim((string)$status);
            if ($language === '' || $status === '') {
                throw new InvalidArgumentException('Product statuses require non-empty language and status codes.');
            }
            $normalizedStatuses[$language] = $status;
        }
        ksort($normalizedStatuses);
        $this->statuses = $normalizedStatuses;
        $seen = [];
        foreach ($values as $value) {
            if (!$value instanceof ProductAttributeValueInterface || isset($seen[$value->getAttributeCode()])) {
                throw new InvalidArgumentException(
                    'Product values must implement the contract and use unique attribute codes.'
                );
            }
            $seen[$value->getAttributeCode()] = true;
        }
        $this->values = array_values($values);
        $this->relations = $relations ?? new ProductRelationState();
        $this->validateRelations();
        $this->authoritativeCollections = $this->normalizeCompleteness($authoritativeCollections);
    }

    private ProductRelationStateInterface $relations;

    public function getSku(): string
    {
        return $this->sku;
    }

    public function getMagentoProductId(): ?int
    {
        return $this->magentoProductId;
    }

    public function getErgonodeSku(): ?string
    {
        return $this->ergonodeSku;
    }

    public function getIdentityMode(): string
    {
        return $this->identityMode;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTemplateCode(): string
    {
        return $this->templateCode;
    }

    public function getStatuses(): array
    {
        return $this->statuses;
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
        return $this->deleted;
    }

    public function isCollectionAuthoritative(string $collection, ?string $identifier = null): bool
    {
        if ($identifier !== null) {
            $specific = $collection . ':' . trim($identifier);
            if (array_key_exists($specific, $this->authoritativeCollections)) {
                return $this->authoritativeCollections[$specific];
            }
        }

        return $this->authoritativeCollections[$collection] ?? false;
    }

    private function validateRelations(): void
    {
        $hasVariableRelations = $this->relations->getBindingCodes() !== [] || $this->relations->getVariantSkus() !== [];
        $hasGroupingRelations = $this->relations->getGroupedChildren() !== [];
        if ($hasVariableRelations && $this->type !== self::TYPE_VARIABLE) {
            throw new InvalidArgumentException('Bindings and variants are valid only for variable products.');
        }
        if ($hasGroupingRelations && $this->type !== self::TYPE_GROUPING) {
            throw new InvalidArgumentException('Grouped children are valid only for grouping products.');
        }
    }

    /** @param array<string, bool> $collections @return array<string, bool> */
    private function normalizeCompleteness(array $collections): array
    {
        $supported = [
            self::COLLECTION_VALUES,
            self::COLLECTION_BINDINGS,
            self::COLLECTION_VARIANTS,
            self::COLLECTION_GROUPED_CHILDREN,
        ];
        $normalized = [];
        foreach ($collections as $collection => $authoritative) {
            if (!is_string($collection) || !is_bool($authoritative)) {
                throw new InvalidArgumentException('Product collection completeness must map names to booleans.');
            }
            [$name, $identifier] = array_pad(explode(':', $collection, 2), 2, null);
            if (!in_array($name, $supported, true)
                || ($identifier !== null && ($name !== self::COLLECTION_VALUES || trim($identifier) === ''))
            ) {
                throw new InvalidArgumentException('Unsupported product collection completeness key: ' . $collection);
            }
            $normalized[$name . ($identifier !== null ? ':' . trim($identifier) : '')] = $authoritative;
        }
        ksort($normalized);

        return $normalized;
    }
}
