<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeAdminUi\Model\Mapping;

class SaveContext
{
    /**
     * @param callable(): array<string, mixed> $save
     * @return array<string, mixed>
     */
    public function execute(callable $save): array
    {
        return $save();
    }
}
