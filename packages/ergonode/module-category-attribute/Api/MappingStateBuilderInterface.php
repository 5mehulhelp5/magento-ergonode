<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttribute\Api;

interface MappingStateBuilderInterface
{
    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, array<string, mixed>> $source
     * @param array<string, array<string, mixed>> $target
     * @return array<int, array<string, mixed>>
     */
    public function attributes(array $rows, array $source, array $target): array;

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, array<string, mixed>> $source
     * @param array<int, array<string, mixed>> $target
     * @return array<int, array<string, mixed>>
     */
    public function options(array $rows, array $source, array $target): array;
}
