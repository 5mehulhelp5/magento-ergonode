<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\E2e\Support;

use RuntimeException;

class ScenarioConfiguration
{
    private function __construct(
        private readonly string $username,
        private readonly string $password,
        private readonly string $seed,
        private readonly int $productLimit,
        private readonly bool $keepFixtures
    ) {
    }

    public static function fromEnvironment(): self
    {
        $username = trim((string)getenv('ERGONODE_E2E_USER'));
        $password = (string)getenv('ERGONODE_E2E_PASSWORD');
        if ($username === '' || $password === '') {
            throw new RuntimeException('ERGONODE_E2E_USER and ERGONODE_E2E_PASSWORD are required.');
        }
        $seed = trim((string)getenv('ERGONODE_E2E_SEED'));
        $limit = (int)(getenv('ERGONODE_E2E_PRODUCT_LIMIT') ?: 100);

        return new self(
            $username,
            $password,
            $seed !== '' ? $seed : 'magento-sample-data',
            max(10, min(500, $limit)),
            getenv('ERGONODE_E2E_KEEP_FIXTURES') === '1'
        );
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getSeed(): string
    {
        return $this->seed;
    }

    public function getProductLimit(): int
    {
        return $this->productLimit;
    }

    public function shouldKeepFixtures(): bool
    {
        return $this->keepFixtures;
    }
}
