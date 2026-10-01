<?php

declare(strict_types=1);

namespace Ergonode\Core\Test\Unit\Model\Config;

use Ergonode\Core\Model\Config\AutomaticSynchronization;
use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\Core\Model\GraphQl\Client;
use Ergonode\Core\Test\Unit\Support\ConnectionTestCase;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;

class AutomaticSynchronizationTest extends ConnectionTestCase
{
    /** @return list<array{string,bool,bool}> */
    public static function cases(): array
    {
        return [['read', true, true], ['write', true, false], ['read', false, false], ['', true, false]];
    }
    #[DataProvider('cases')]
    public function testAutomaticPermission(string $mode, bool $enabled, bool $expected): void
    {
        $config = $this->createStub(ConfigProvider::class);
        $config->method('getMode')->willReturn($mode);
        $config->method('isEnabled')->willReturn($enabled);
        $client = $this->createMock(Client::class);
        $client->expects($expected ? self::once() : self::never())->method('isConnectionAvailable')->willReturn(true);
        self::assertSame($expected, (new AutomaticSynchronization($config, $client))->isAllowed());
    }

    #[DataProvider('unavailableConnections')]
    public function testUnavailableConnectionNeverAllowsWorkOrLogs(string $scenario): void
    {
        $policy = $this->unavailableAutomation($scenario);
        self::assertFalse($policy->isAllowed());
        self::assertFalse($policy->isAllowed());
    }

    public function testAvailabilityIsRecheckedAfterFailure(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::exactly(3))->method('isConnectionAvailable')
            ->willReturnOnConsecutiveCalls(false, true, false);
        $policy = new AutomaticSynchronization($this->connectionConfig('valid'), $client);
        self::assertFalse($policy->isAllowed());
        self::assertTrue($policy->isAllowed());
        self::assertFalse($policy->isAllowed());
    }

    public function testUnexpectedFailureIsNotTreatedAsMissingConfiguration(): void
    {
        $client = $this->createStub(Client::class);
        $client->method('isConnectionAvailable')->willThrowException(new LogicException('Programming defect'));
        $this->expectException(LogicException::class);
        (new AutomaticSynchronization($this->connectionConfig('valid'), $client))->isAllowed();
    }
}
