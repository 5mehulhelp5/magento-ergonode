<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Api;

interface WriteScopeAttributeCacheRefresherInterface
{
    /**
     * Refresh the attribute cache using the credential that authorizes writes.
     *
     * @return void
     */
    public function refreshAttributesWriteScope(): void;

    /**
     * Refresh an attribute option cache using the credential that authorizes writes.
     *
     * @param string $attributeCode
     * @return void
     */
    public function refreshOptionsWriteScope(string $attributeCode): void;
}
