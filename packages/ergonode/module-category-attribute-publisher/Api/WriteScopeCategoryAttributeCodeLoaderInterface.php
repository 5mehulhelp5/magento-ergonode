<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisher\Api;

interface WriteScopeCategoryAttributeCodeLoaderInterface
{
    /** @return string[] */
    public function loadWriteScope(): array;
}
