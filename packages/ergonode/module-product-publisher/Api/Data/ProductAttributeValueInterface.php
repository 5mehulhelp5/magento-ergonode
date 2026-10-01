<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Api\Data;

interface ProductAttributeValueInterface
{
    /**
     * @return string
     */
    public function getAttributeCode(): string;

    /**
     * @return string
     */
    public function getType(): string;

    /**
     * @return array<string, float|string|string[]>
     */
    public function getTranslations(): array;

    /**
     * @return string|null
     */
    public function getTwoWayRelation(): ?string;
}
