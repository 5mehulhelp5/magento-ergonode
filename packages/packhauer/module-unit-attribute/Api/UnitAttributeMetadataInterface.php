<?php

declare(strict_types=1);

namespace PackHauer\UnitAttribute\Api;

use Magento\Framework\Exception\LocalizedException;

interface UnitAttributeMetadataInterface
{
    public const string ADDITIONAL_DATA_KEY = 'vendivo_unit';

    /**
     * @param string $attributeCode
     * @return array{name: string, symbol: string}|null
     * @throws LocalizedException
     */
    public function get(string $attributeCode): ?array;

    /**
     * @param string|null $additionalData
     * @param string $name
     * @param string $symbol
     * @return string
     * @throws LocalizedException
     */
    public function withUnit(?string $additionalData, string $name, string $symbol): string;

    /**
     * @param string|null $additionalData
     * @return string|null
     * @throws LocalizedException
     */
    public function withoutUnit(?string $additionalData): ?string;
}
