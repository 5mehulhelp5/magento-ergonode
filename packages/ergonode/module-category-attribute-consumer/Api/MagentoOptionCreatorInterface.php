<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Api;

interface MagentoOptionCreatorInterface
{
    /**
     * @param string $attributeCode
     * @param string $label
     * @return array<string, mixed>
     */
    public function create(string $attributeCode, string $label): array;
}
