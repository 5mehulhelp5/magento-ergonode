<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttribute\Api;

interface MagentoAttributeProviderInterface
{
    /** @return array<int, array<string, mixed>> */
    public function getAttributes(): array;

    /** @return array<string, array<string, mixed>> */
    public function getAttributeMap(): array;

    /**
     * @param string $code
     * @return array<string, mixed>|null
     */
    public function getAttribute(string $code): ?array;
}
