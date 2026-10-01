<?php

declare(strict_types=1);

namespace Ergonode\Consumer\Test\Unit\Model\Config;

use Ergonode\Consumer\Model\Config\ConnectionMode;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class ConnectionModeTest extends TestCase
{
    public function testDecryptsOnlyTheRequestedEnvironmentKey(): void
    {
        foreach (['test', 'production'] as $environment) {
            $scope = $this->createMock(ScopeConfigInterface::class);
            $scope->expects(self::once())->method('getValue')
                ->with('ergonode_connection/' . $environment . '/consumer/api_key')->willReturn('encrypted');
            $encryptor = $this->createMock(EncryptorInterface::class);
            $encryptor->expects(self::once())->method('decrypt')->with('encrypted')->willReturn(' secret ');
            $mode = new ConnectionMode($scope, $encryptor);
            self::assertSame('read', $mode->getCode());
            self::assertSame(false, $mode->allowsWrites());
            self::assertSame('secret', $mode->getApiKey($environment));
        }
    }

    public function testRejectsUnknownEnvironmentBeforeReadingSecrets(): void
    {
        $scope = $this->createMock(ScopeConfigInterface::class);
        $scope->expects(self::never())->method('getValue');
        $mode = new ConnectionMode($scope, $this->createStub(EncryptorInterface::class));
        $this->expectException(LocalizedException::class);
        $mode->getApiKey('other');
    }
}
