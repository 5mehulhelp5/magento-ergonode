<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\Unit\Support;

use Ergonode\ProductPublisher\Test\E2e\Support\ScenarioConfiguration;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ScenarioConfigurationTest extends TestCase
{
    private const array ENVIRONMENT_VARIABLES = [
        'ERGONODE_E2E_USER',
        'ERGONODE_E2E_PASSWORD',
        'ERGONODE_E2E_SEED',
        'ERGONODE_E2E_PRODUCT_LIMIT',
        'ERGONODE_E2E_KEEP_FIXTURES',
    ];

    /** @var array<string, string|false> */
    private array $originalEnvironment = [];

    protected function setUp(): void
    {
        foreach (self::ENVIRONMENT_VARIABLES as $name) {
            $this->originalEnvironment[$name] = getenv($name);
            putenv($name);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnvironment as $name => $value) {
            putenv($value === false ? $name : $name . '=' . $value);
        }
    }

    public function testRequiresOnlyUserCredentialsToStartTheManualJourney(): void
    {
        putenv('ERGONODE_E2E_USER=owner@example.test');
        putenv('ERGONODE_E2E_PASSWORD=secret');

        $configuration = ScenarioConfiguration::fromEnvironment();

        self::assertSame('owner@example.test', $configuration->getUsername());
        self::assertSame('secret', $configuration->getPassword());
        self::assertSame('magento-sample-data', $configuration->getSeed());
        self::assertSame(100, $configuration->getProductLimit());
        self::assertFalse($configuration->shouldKeepFixtures());
    }

    public function testRejectsMissingUserCredentials(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ERGONODE_E2E_USER and ERGONODE_E2E_PASSWORD are required.');

        ScenarioConfiguration::fromEnvironment();
    }

    public function testNormalizesOptionalScenarioSettings(): void
    {
        putenv('ERGONODE_E2E_USER=owner@example.test');
        putenv('ERGONODE_E2E_PASSWORD=secret');
        putenv('ERGONODE_E2E_SEED=custom-seed');
        putenv('ERGONODE_E2E_PRODUCT_LIMIT=999');
        putenv('ERGONODE_E2E_KEEP_FIXTURES=1');

        $configuration = ScenarioConfiguration::fromEnvironment();

        self::assertSame('custom-seed', $configuration->getSeed());
        self::assertSame(500, $configuration->getProductLimit());
        self::assertTrue($configuration->shouldKeepFixtures());
    }
}
