<?php

declare(strict_types=1);

namespace Ergonode\Attribute\Api;

interface AttributeTypeCompatibilityInterface
{
    /**
     * @param string $ergonodeType
     * @param string $magentoType
     * @return bool
     */
    public function canMapAttributes(string $ergonodeType, string $magentoType): bool;

    /**
     * @param string $ergonodeType
     * @param string $magentoType
     * @return bool
     */
    public function canMapOptions(string $ergonodeType, string $magentoType): bool;

    /**
     * @return array<string, string[]>
     */
    public function getAttributeCompatibilityMap(): array;
}
