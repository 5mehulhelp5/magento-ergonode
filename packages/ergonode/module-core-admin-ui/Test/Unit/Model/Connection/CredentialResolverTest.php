<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Test\Unit\Model\Connection;

use Ergonode\Core\Api\ConnectionModeInterface;
use Ergonode\Core\Model\Config\ConnectionModePool;
use Ergonode\CoreAdminUi\Model\Connection\CredentialResolver;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class CredentialResolverTest extends TestCase
{
    public function testSubmittedKeyDoesNotReadStoredCredentials(): void
    {
        $mode = $this->createMock(ConnectionModeInterface::class);
        $mode->expects(self::never())->method('getApiKey');
        $resolver = new CredentialResolver(new ConnectionModePool(['write' => $mode]));
        self::assertSame('new-key', $resolver->resolveApiKey('write', 'production', ' new-key '));
    }

    public function testMaskedKeyUsesExplicitModeAndEnvironment(): void
    {
        foreach (['read', 'write'] as $code) {
            foreach (['test', 'production'] as $environment) {
                $mode = $this->createMock(ConnectionModeInterface::class);
                $mode->expects(self::once())->method('getApiKey')->with($environment)->willReturn('profile-key');
                $resolver = new CredentialResolver(new ConnectionModePool([$code => $mode]));
                self::assertSame('profile-key', $resolver->resolveApiKey($code, $environment, '******'));
            }
        }
    }

    public function testUnknownModeIsRejectedEvenForSubmittedKey(): void
    {
        $this->expectException(LocalizedException::class);
        (new CredentialResolver(new ConnectionModePool()))->resolveApiKey('missing', 'test', 'key');
    }

    public function testUnknownEnvironmentIsRejectedEvenForSubmittedKey(): void
    {
        $resolver = new CredentialResolver(new ConnectionModePool([
            'read' => $this->createStub(ConnectionModeInterface::class),
        ]));
        $this->expectException(LocalizedException::class);
        $resolver->resolveApiKey('read', 'missing', 'key');
    }
}
