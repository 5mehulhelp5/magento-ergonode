<?php

declare(strict_types=1);

namespace Ergonode\Core\Api;

interface ConnectionModeInterface
{
    /**
     * @return string
     */
    public function getCode(): string;

    /**
     * @return string
     */
    public function getLabel(): string;

    /**
     * @return bool
     */
    public function allowsWrites(): bool;

    /**
     * @param string $environment
     * @return string
     */
    public function getApiKey(string $environment): string;
}
