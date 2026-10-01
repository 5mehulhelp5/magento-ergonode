<?php

declare(strict_types=1);

namespace Ergonode\Core\Test\Unit\Model\Readiness;

use Ergonode\Core\Api\Data\ReadinessContextInterface;
use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\Core\Model\Data\ReadinessContext;
use Ergonode\Core\Model\Readiness\ConnectionConfigurationCheck;
use Ergonode\Core\Model\Readiness\ReadinessIssueFactory;
use PHPUnit\Framework\TestCase;

class ConnectionConfigurationCheckTest extends TestCase
{
    public function testOverviewRequiresReadConfiguration(): void
    {
        $configProvider = $this->createStub(ConfigProvider::class);
        $check = new ConnectionConfigurationCheck($configProvider, new ReadinessIssueFactory());

        $issues = $check->check(new ReadinessContext(ReadinessContextInterface::OPERATION_OVERVIEW));

        self::assertSame([
            'connection.graphql_url_missing',
            'connection.unavailable',
        ], array_map(static fn ($issue): string => $issue->getCode(), $issues));
    }

    public function testProductPublicationOnlyRequiresSharedEndpoint(): void
    {
        $configProvider = $this->createStub(ConfigProvider::class);
        $configProvider->method('getGraphQlUrl')->willReturn('https://ergonode.example/graphql');
        $check = new ConnectionConfigurationCheck($configProvider, new ReadinessIssueFactory());

        self::assertSame([], $check->check(
            new ReadinessContext(ReadinessContextInterface::OPERATION_PUBLISH_PRODUCTS)
        ));
    }
}
