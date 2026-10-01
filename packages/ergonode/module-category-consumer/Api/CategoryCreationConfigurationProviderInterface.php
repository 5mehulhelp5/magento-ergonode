<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Api;

interface CategoryCreationConfigurationProviderInterface
{
    /**
     * @return array{
     *     attributes_enabled: bool,
     *     fixed_values: array<string, int>,
     *     mapped_attribute_codes: string[]
     * }
     */
    public function get(): array;
}
