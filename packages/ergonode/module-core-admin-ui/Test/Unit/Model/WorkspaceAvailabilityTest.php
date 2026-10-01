<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Test\Unit\Model;

use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\Core\Model\Readiness\ConnectionConfigurationCheck;
use Ergonode\Core\Model\Readiness\ReadinessIssueFactory;
use Ergonode\CoreAdminUi\Model\WorkspaceAvailability;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class WorkspaceAvailabilityTest extends TestCase
{
    public function testConfiguredReadModeDoesNotRequireWrites(): void
    {
        $config = $this->createMock(ConfigProvider::class);
        $config->method('getGraphQlUrl')->willReturn('https://example.test/graphql');
        $config->method('isEnabled')->willReturn(true);
        $config->method('getApiKey')->willReturn('configured');
        $config->expects(self::never())->method('allowsWrites');
        self::assertSame([], $this->availability($config)->getProblems());
    }

    public function testDisabledConnectionDoesNotReadCredentials(): void
    {
        $config = $this->createMock(ConfigProvider::class);
        $config->method('getGraphQlUrl')->willReturn('https://example.test/graphql');
        $config->method('isEnabled')->willReturn(false);
        $config->expects(self::never())->method('getApiKey');
        self::assertSame([
            'Enable the active Ergonode connection and choose an available operating mode.',
        ], $this->availability($config)->getProblems());
    }

    public function testMissingUrlAndKeyAreBothExplained(): void
    {
        $config = $this->createStub(ConfigProvider::class);
        $config->method('getGraphQlUrl')->willReturn('');
        $config->method('isEnabled')->willReturn(true);
        $config->method('getApiKey')->willReturn('');
        self::assertSame([
            'Configure the Ergonode GraphQL URL.',
            'Configure the API key for the active environment and operating mode.',
        ], $this->availability($config)->getProblems());
    }

    public function testInvalidEnvironmentBecomesAnActionableNotice(): void
    {
        $config = $this->createStub(ConfigProvider::class);
        $config->method('getGraphQlUrl')->willThrowException(
            new LocalizedException(__('Choose a valid Ergonode environment.'))
        );
        self::assertSame(['Choose a valid Ergonode environment.'], $this->availability($config)->getProblems());
    }

    private function availability(ConfigProvider $config): WorkspaceAvailability
    {
        return new WorkspaceAvailability(new ConnectionConfigurationCheck($config, new ReadinessIssueFactory()));
    }
}
