<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Api\Rest;

interface ConnectionManagementInterface
{
    /**
     * @param string $email
     * @param string $password
     * @return void
     */
    public function login(string $email, string $password): void;

    /** @return void */
    public function disconnect(): void;

    /** @return array{authenticated: bool, email: string} */
    public function status(): array;
}
