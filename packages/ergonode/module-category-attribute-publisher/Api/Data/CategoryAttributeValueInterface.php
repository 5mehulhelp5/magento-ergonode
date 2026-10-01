<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisher\Api\Data;

interface CategoryAttributeValueInterface
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
}
