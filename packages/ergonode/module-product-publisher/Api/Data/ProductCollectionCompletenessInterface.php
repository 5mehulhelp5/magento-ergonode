<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Api\Data;

/**
 * Optional source completeness carried by desired product state.
 *
 * Reconcile may remove remote-only entries only for collections declared
 * authoritative for the exact source scope. Values can be declared complete
 * globally or for one attribute code.
 */
interface ProductCollectionCompletenessInterface extends ProductStateInterface
{
    public const string COLLECTION_VALUES = 'values';
    public const string COLLECTION_BINDINGS = 'bindings';
    public const string COLLECTION_VARIANTS = 'variants';
    public const string COLLECTION_GROUPED_CHILDREN = 'grouped_children';

    /**
     * @param string $collection
     * @param string|null $identifier
     * @return bool
     */
    public function isCollectionAuthoritative(string $collection, ?string $identifier = null): bool;
}
