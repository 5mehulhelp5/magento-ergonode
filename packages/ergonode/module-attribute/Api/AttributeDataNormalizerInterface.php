<?php

declare(strict_types=1);

namespace Ergonode\Attribute\Api;

interface AttributeDataNormalizerInterface
{
    /**
     * @param mixed $items
     * @return array<string, string>
     */
    public function translations(mixed $items): array;

    /**
     * @param string $type
     * @param array<string, mixed> $node
     * @return array<string, bool|string>
     */
    public function parameters(string $type, array $node): array;
}
