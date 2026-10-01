<?php

declare(strict_types=1);

namespace Ergonode\PublisherAdminUi\Test\Unit\Model;

use Ergonode\Core\Api\ConnectionModeInterface;
use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\Core\Model\Config\ConnectionModePool;
use Ergonode\PublisherAdminUi\Model\WriteReadinessProvider;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\AuthorizationInterface;
use PHPUnit\Framework\TestCase;

class WriteReadinessProviderTest extends TestCase
{
    public function testReadModeDoesNotReportAMissingWriteKey(): void
    {
        foreach (['test', 'production'] as $environment) {
            $result = $this->provider($environment, 'read', true, 'private-key')->getStatus();
            self::assertFalse($result['ready']);
            self::assertStringContainsString('Choose Read and write mode', $result['message']);
            self::assertStringNotContainsString('missing', $result['message']);
            self::assertStringNotContainsString('disabled', $result['message']);
            self::assertStringNotContainsString('Write Operations', $result['message']);
        }
    }

    public function testUnavailableConnectionDoesNotInspectCredentials(): void
    {
        foreach (['test', 'production'] as $environment) {
            foreach ([['write', false], ['unavailable', true]] as [$mode, $enabled]) {
                $result = $this->provider($environment, $mode, $enabled, 'private-key')->getStatus();
                self::assertFalse($result['ready']);
                self::assertStringContainsString('Enable the active Ergonode connection', $result['message']);
                self::assertStringNotContainsString('missing', $result['message']);
            }
        }
    }

    public function testWriteModeChecksOnlyTheActiveEnvironmentKey(): void
    {
        foreach (['test', 'production'] as $environment) {
            foreach (['', '  ', 'private-key'] as $key) {
                $result = $this->provider($environment, 'write', true, $key)->getStatus();
                self::assertSame(trim($key) !== '', $result['ready']);
                self::assertSame('/configuration', $result['configuration_url']);
                self::assertStringNotContainsString('private-key', json_encode($result));
                if ($result['ready']) {
                    self::assertSame('', $result['message']);
                } else {
                    self::assertStringContainsString(
                        'The read-and-write API key for the active environment is missing.',
                        $result['message']
                    );
                    self::assertStringContainsString('Connection configuration', $result['message']);
                }
            }
        }
    }

    public function testDoesNotOfferConfigurationToAnUnauthorizedAdministrator(): void
    {
        $result = $this->provider('test', 'read', true, '', false)->getStatus();
        self::assertSame('', $result['configuration_url']);
        self::assertStringContainsString('Ask an administrator', $result['message']);
    }

    private function provider(
        string $environment,
        string $selectedMode,
        bool $enabled,
        string $key,
        bool $canConfigure = true
    ): WriteReadinessProvider {
        $scope = $this->createStub(ScopeConfigInterface::class);
        $scope->method('getValue')->willReturnMap([
            [ConfigProvider::XML_PATH_ENVIRONMENT, 'default', null, $environment],
            [ConfigProvider::XML_PATH_MODE, 'default', null, $selectedMode],
        ]);
        $scope->method('isSetFlag')->willReturnCallback(
            static fn(string $path): bool => $path === ConfigProvider::XML_PATH_ENABLED
                ? $enabled
                : true
        );
        $read = $this->createMock(ConnectionModeInterface::class);
        $read->method('allowsWrites')->willReturn(false);
        $read->expects(self::never())->method('getApiKey');
        $write = $this->createMock(ConnectionModeInterface::class);
        $write->method('allowsWrites')->willReturn(true);
        $write->expects($enabled && $selectedMode === 'write' ? self::once() : self::never())
            ->method('getApiKey')->with($environment)->willReturn($key);
        $configuration = new ConfigProvider($scope, new ConnectionModePool(['read' => $read, 'write' => $write]));
        $authorization = $this->createStub(AuthorizationInterface::class);
        $authorization->method('isAllowed')->willReturn($canConfigure);
        $url = $this->createMock(UrlInterface::class);
        $url->expects($canConfigure ? self::once() : self::never())->method('getUrl')
            ->with('adminhtml/system_config/edit', ['section' => 'ergonode_connection'])
            ->willReturn('/configuration');

        return new WriteReadinessProvider($configuration, $authorization, $url);
    }
}
