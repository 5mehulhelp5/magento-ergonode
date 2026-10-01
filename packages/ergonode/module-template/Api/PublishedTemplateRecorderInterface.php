<?php

declare(strict_types=1);

namespace Ergonode\Template\Api;

interface PublishedTemplateRecorderInterface
{
    /**
     * @param string $code
     * @param array<string, string> $names
     * @return void
     */
    public function record(string $code, array $names): void;
}
