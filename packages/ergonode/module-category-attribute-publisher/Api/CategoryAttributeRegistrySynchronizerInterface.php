<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisher\Api;

interface CategoryAttributeRegistrySynchronizerInterface
{
    /**
     * @param string $attributeCode
     * @return void
     */
    public function ensure(string $attributeCode): void;
}
