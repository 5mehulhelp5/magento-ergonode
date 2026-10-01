<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Api\Rest;

interface ConnectionStorageInterface
{
    /**
     * Decrypted credentials. Never expose them in Admin responses or logs.
     * @param string $profile
     * @return array<string, mixed>|null
     */
    public function get(string $profile): ?array;

    /**
     * @param string $profile
     * @param array<string, mixed> $connection
     * @return void
     */
    public function save(string $profile, array $connection): void;

    /**
     * @param string $profile
     * @return void
     */
    public function delete(string $profile): void;
}
