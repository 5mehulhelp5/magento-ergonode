<?php

declare(strict_types=1);

namespace Ergonode\Core\Test\Unit\Support;

use Ergonode\Core\Api\ConnectionModeInterface;
use Ergonode\Core\Api\GraphQlRequestLimiterInterface;
use Ergonode\Core\Model\Config\AutomaticSynchronization;
use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\Core\Model\Config\ConnectionModePool;
use Ergonode\Core\Model\GraphQl\Client;
use Ergonode\Core\Model\GraphQl\MutationGuard;
use Ergonode\Core\Model\GraphQl\ReadOnlyGuard;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/** Shared connection fixtures, with no knowledge of consuming domains. */
abstract class ConnectionTestCase extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function unavailableConnections(): iterable
    {
        foreach ([
            'disabled', 'write', 'missing-mode', 'unknown-mode', 'invalid-environment',
            'empty-url', 'whitespace-url', 'malformed-url', 'non-http-url', 'empty-key', 'whitespace-key', 'empty-both',
            '401', '403', '404', '429', '500', 'timeout', 'html', 'invalid-json-shape', 'graphql-error',
        ] as $scenario) {
            yield $scenario => [$scenario];
        }
    }

    protected function silentLogger(): LoggerInterface
    {
        $logger = $this->createMock(LoggerInterface::class);
        foreach (['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency', 'log'] as $method) {
            $logger->expects(self::never())->method($method);
        }
        return $logger;
    }

    protected function unavailableAutomation(string $scenario, int $runs = 2): AutomaticSynchronization
    {
        return new AutomaticSynchronization(
            $this->connectionConfig($scenario),
            $this->connectionClient($scenario, $runs)
        );
    }

    protected function connectionConfig(string $scenario): ConfigProvider
    {
        $mode = $this->createStub(ConnectionModeInterface::class);
        $mode->method('getApiKey')->willReturn(match ($scenario) {
            'empty-key', 'empty-both' => '', 'whitespace-key' => '  ', default => 'test-key',
        });
        $scope = $this->createStub(ScopeConfigInterface::class);
        $scope->method('isSetFlag')->willReturn($scenario !== 'disabled');
        $scope->method('getValue')->willReturnCallback(static fn (string $path): string => match ($path) {
            ConfigProvider::XML_PATH_MODE => match ($scenario) {
                'write' => 'write', 'missing-mode' => '', 'unknown-mode' => 'unknown', default => 'read',
            },
            ConfigProvider::XML_PATH_ENVIRONMENT => $scenario === 'invalid-environment' ? 'invalid' : 'test',
            ConfigProvider::XML_PATH_GRAPHQL_URL => match ($scenario) {
                'empty-url', 'empty-both' => '', 'whitespace-url' => '  ', 'malformed-url' => 'ergonode.invalid',
                'non-http-url' => 'ftp://ergonode.invalid', default => 'https://ergonode.invalid',
            },
            default => '',
        });
        return new ConfigProvider($scope, new ConnectionModePool(['read' => $mode, 'write' => $mode]));
    }

    protected function connectionClient(string $scenario, int $runs): Client
    {
        $remote = in_array($scenario, [
            '401', '403', '404', '429', '500', 'timeout', 'html', 'invalid-json-shape', 'graphql-error', 'valid',
        ], true);
        $curl = $this->createMock(Curl::class);
        $post = $curl->expects(self::exactly($remote ? $runs : 0))->method('post');
        if ($scenario === 'timeout') {
            $post->willThrowException(new RuntimeException('Connection timed out'));
        }
        $curl->method('getStatus')->willReturn(ctype_digit($scenario) ? (int)$scenario : 200);
        $curl->method('getHeaders')->willReturn([]);
        $curl->method('getBody')->willReturn(match ($scenario) {
            'html' => '<html>Wrong endpoint</html>', 'invalid-json-shape' => '[]',
            'graphql-error' => '{"errors":[{"message":"Access denied"}]}',
            default => '{"data":{"__typename":"Query"}}',
        });
        $limiter = $this->createMock(GraphQlRequestLimiterInterface::class);
        $limiter->expects(self::exactly($remote ? $runs : 0))->method('throttle');

        return new Client(
            $curl,
            new Json(),
            $this->connectionConfig($scenario),
            new ReadOnlyGuard(),
            $this->createStub(MutationGuard::class),
            $limiter,
            $this->silentLogger()
        );
    }
}
