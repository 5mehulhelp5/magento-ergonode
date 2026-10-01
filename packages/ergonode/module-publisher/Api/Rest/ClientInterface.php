<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Api\Rest;

interface ClientInterface
{
    /**
     * @param string $method
     * @param string $path
     * @param array<string, mixed>|null $payload
     * @return array<string|int, mixed>
     */
    public function request(string $method, string $path, ?array $payload = null): array;
}
