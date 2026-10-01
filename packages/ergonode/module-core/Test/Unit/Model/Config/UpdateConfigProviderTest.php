<?php

declare(strict_types=1);

namespace Ergonode\Core\Test\Unit\Model\Config;

use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\Core\Model\Config\UpdateConfigProvider;
use PHPUnit\Framework\TestCase;

class UpdateConfigProviderTest extends TestCase
{
    public function testReadModeDoesNotExposeAWriteCredential(): void
    {
        $config = $this->createMock(ConfigProvider::class);
        $config->method('allowsWrites')->willReturn(false);
        $config->expects(self::never())->method('getApiKey');
        $provider = new UpdateConfigProvider($config);
        self::assertFalse($provider->isEnabled());
        self::assertSame('', $provider->getApiKey());
    }

    public function testWritesUseTheSameCredentialAsOrdinaryQueries(): void
    {
        $config = $this->createMock(ConfigProvider::class);
        $config->method('allowsWrites')->willReturn(true);
        $config->expects(self::once())->method('getApiKey')->willReturn('active-key');
        $provider = new UpdateConfigProvider($config);
        self::assertTrue($provider->isEnabled());
        self::assertSame('active-key', $provider->getApiKey());
    }
}
