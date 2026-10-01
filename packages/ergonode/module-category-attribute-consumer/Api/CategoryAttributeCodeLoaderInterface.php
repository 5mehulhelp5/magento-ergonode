<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Api;

interface CategoryAttributeCodeLoaderInterface
{
    /** @return string[] */
    public function load(): array;
}
