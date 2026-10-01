<?php

declare(strict_types=1);

namespace Ergonode\Core\Api\Data;

interface ReadinessContextInterface
{
    public const string OPERATION_OVERVIEW = 'overview';

    public const string OPERATION_PUBLISH_PRODUCTS = 'publish_products';

    /**
     * Return the operation evaluated by the readiness checks.
     *
     * @return string
     */
    public function getOperation(): string;

    /**
     * Return selected identifiers for a domain, or an empty list for the complete domain.
     *
     * @param string $domain
     * @return string[]
     */
    public function getEntityIdentifiers(string $domain): array;
}
