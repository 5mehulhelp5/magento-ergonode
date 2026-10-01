<?php

declare(strict_types=1);

namespace Ergonode\Core\Test\Unit\Model\Config;

use Ergonode\Core\Api\ConnectionModeInterface;
use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\Core\Model\Config\ConnectionModePool;
use Ergonode\Core\Model\Config\UpdateConfigProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use LogicException;

class ConfigProviderTest extends TestCase
{
    public function testEachModeUsesExactlyOneEnvironmentCredential(): void
    {
        foreach (['test', 'production'] as $environment) {
            foreach (['read', 'write'] as $selected) {
                $mode = $this->createMock(ConnectionModeInterface::class);
                $mode->method('allowsWrites')->willReturn($selected === 'write');
                $mode->expects(self::exactly($selected === 'write' ? 2 : 1))
                    ->method('getApiKey')->with($environment)->willReturn('selected-key');
                $other = $this->createMock(ConnectionModeInterface::class);
                $other->expects(self::never())->method('getApiKey');
                $modes = [$selected => $mode, ($selected === 'read' ? 'write' : 'read') => $other];
                $config = $this->scope($environment, $selected);
                $provider = new ConfigProvider($config, new ConnectionModePool($modes));

                self::assertTrue($provider->isEnabled());
                self::assertSame('https://' . $environment . '.ergonode.cloud', $provider->getGraphQlUrl());
                self::assertSame($environment === 'test' ? 10 : 20, $provider->getRequestsPerMinute());
                self::assertSame(10, $provider->getRequestsPerMinute('test'));
                self::assertSame(20, $provider->getRequestsPerMinute('production'));
                self::assertSame('selected-key', $provider->getApiKey());
                $updates = new UpdateConfigProvider($provider);
                self::assertSame($selected === 'write', $updates->isEnabled());
                self::assertSame($selected === 'write' ? 'selected-key' : '', $updates->getApiKey());
            }
        }
    }

    public function testUnavailableModeNeverFallsBackToInstalledMode(): void
    {
        $mode = $this->createMock(ConnectionModeInterface::class);
        $mode->expects(self::never())->method('getApiKey');
        $provider = new ConfigProvider($this->scope('test', 'missing'), new ConnectionModePool(['read' => $mode]));
        self::assertFalse($provider->isEnabled());
        self::assertFalse($provider->allowsWrites());
        $this->expectException(LocalizedException::class);
        $provider->getApiKey();
    }

    public function testInvalidEnvironmentIsRejectedInsteadOfSelectingAnotherProfile(): void
    {
        $provider = new ConfigProvider($this->scope('invalid', 'read'), new ConnectionModePool());
        $this->expectException(LocalizedException::class);
        $provider->getGraphQlUrl();
    }

    public function testDisabledConnectionCannotUseEitherEnvironmentsCredentials(): void
    {
        foreach (['test', 'production'] as $environment) {
            $scope = $this->createMock(ScopeConfigInterface::class);
            $scope->method('getValue')->willReturnMap([
                [ConfigProvider::XML_PATH_ENVIRONMENT, 'default', null, $environment],
                [ConfigProvider::XML_PATH_MODE, 'default', null, 'write'],
            ]);
            $scope->method('isSetFlag')->willReturnCallback(
                static fn(string $path): bool => $path !== ConfigProvider::XML_PATH_ENABLED
            );
            $mode = $this->createMock(ConnectionModeInterface::class);
            $mode->expects(self::never())->method('getApiKey');
            $provider = new ConfigProvider($scope, new ConnectionModePool(['write' => $mode]));
            self::assertFalse($provider->isEnabled());
            self::assertFalse($provider->allowsWrites());
            try {
                $provider->getApiKey();
                self::fail('A disabled connection must not expose runtime credentials.');
            } catch (LocalizedException $exception) {
                self::assertStringContainsString('disabled', $exception->getMessage());
            }
        }
    }

    private function scope(string $environment, string $mode): ScopeConfigInterface
    {
        $scope = $this->createStub(ScopeConfigInterface::class);
        $scope->method('isSetFlag')->willReturnCallback(
            static fn(string $path): bool => $path === ConfigProvider::XML_PATH_ENABLED
        );
        $scope->method('getValue')->willReturnCallback(static fn(string $path): string => match ($path) {
            'ergonode_connection/general/environment' => $environment,
            'ergonode_connection/general/mode' => $mode,
            'ergonode_connection/test/url' => ' https://test.ergonode.cloud ',
            'ergonode_connection/production/url' => ' https://production.ergonode.cloud ',
            'ergonode_connection/test/requests_per_minute' => '10',
            'ergonode_connection/production/requests_per_minute' => '20',
            default => throw new LogicException('Unexpected configuration path: ' . $path),
        });

        return $scope;
    }
}
