<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\Unit\Model\Readiness;

use Ergonode\ProductPublisher\Model\Readiness\ProductPublicationConfigurationCheck;
use Ergonode\Core\Api\Data\ReadinessContextInterface;
use Ergonode\Core\Api\Data\ReadinessIssueInterface;
use Ergonode\Core\Api\UpdateConfigurationProviderInterface;
use Ergonode\Core\Model\Data\ReadinessContext;
use Ergonode\Core\Model\Readiness\ReadinessIssueFactory;
use PHPUnit\Framework\TestCase;

class ProductPublicationConfigurationCheckTest extends TestCase
{
    public function testConfiguredWriteConnectionIsReady(): void
    {
        $updateConfiguration = $this->createStub(UpdateConfigurationProviderInterface::class);
        $updateConfiguration->method('isEnabled')->willReturn(true);
        $updateConfiguration->method('getApiKey')->willReturn('write-key');
        $check = new ProductPublicationConfigurationCheck(
            $updateConfiguration,
            new ReadinessIssueFactory()
        );

        self::assertSame([], $check->check(
            new ReadinessContext(ReadinessContextInterface::OPERATION_PUBLISH_PRODUCTS)
        ));
    }

    public function testWriteConnectionFailuresAreOptionalWarningsInOverview(): void
    {
        $updateConfiguration = $this->createStub(UpdateConfigurationProviderInterface::class);
        $check = new ProductPublicationConfigurationCheck(
            $updateConfiguration,
            new ReadinessIssueFactory()
        );

        $issues = $check->check(new ReadinessContext(ReadinessContextInterface::OPERATION_OVERVIEW));

        self::assertSame([
            'products.write_operations_disabled',
            'products.write_api_key_missing',
        ], array_map(static fn ($issue): string => $issue->getCode(), $issues));
        self::assertSame(['connection', 'connection'], array_map(
            static fn ($issue): string => $issue->getDomain(),
            $issues
        ));
        self::assertSame([
            ReadinessIssueInterface::SEVERITY_WARNING,
            ReadinessIssueInterface::SEVERITY_WARNING,
        ], array_map(static fn ($issue): string => $issue->getSeverity(), $issues));
        self::assertSame(['connection', 'connection'], array_map(
            static fn ($issue): ?string => $issue->getRemediation(),
            $issues
        ));
    }

    public function testWriteConnectionFailuresStillBlockProductPublication(): void
    {
        $updateConfiguration = $this->createStub(UpdateConfigurationProviderInterface::class);
        $check = new ProductPublicationConfigurationCheck(
            $updateConfiguration,
            new ReadinessIssueFactory()
        );

        $issues = $check->check(new ReadinessContext(
            ReadinessContextInterface::OPERATION_PUBLISH_PRODUCTS
        ));

        self::assertSame([
            ReadinessIssueInterface::SEVERITY_BLOCKER,
            ReadinessIssueInterface::SEVERITY_BLOCKER,
        ], array_map(static fn ($issue): string => $issue->getSeverity(), $issues));
    }
}
