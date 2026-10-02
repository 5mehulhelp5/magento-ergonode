<?php

declare(strict_types=1);

namespace Ergonode\Media\Api;

interface ImageAttributeOptionsInterface
{
    /** @return array<string,string> Ergonode Image attribute codes and labels. */
    public function getOptions(): array;
}
