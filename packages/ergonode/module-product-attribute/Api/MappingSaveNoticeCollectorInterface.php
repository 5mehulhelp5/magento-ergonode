<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Api;

interface MappingSaveNoticeCollectorInterface
{
    /**
     * Add a warning that must be returned with the current mapping save response.
     *
     * @param  string $message
     * @return void
     */
    public function addWarning(string $message): void;

    /**
     * Return and clear warnings collected during the current mapping save request.
     *
     * @return string[]
     */
    public function consumeWarnings(): array;
}
