<?php

declare(strict_types=1);

namespace Ergonode\TemplatePublisher\Api;

interface TemplateCreatorInterface
{
    /**
     * @param string $code
     * @param array<string, string> $names
     * @return void
     */
    public function create(string $code, array $names): void;
}
