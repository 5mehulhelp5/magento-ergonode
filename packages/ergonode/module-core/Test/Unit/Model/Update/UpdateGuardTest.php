<?php

declare(strict_types=1);

namespace Ergonode\Core\Test\Unit\Model\Update;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Ergonode\Core\Model\Config\UpdateConfigProvider;
use Ergonode\Core\Model\Update\UpdateGuard;

class UpdateGuardTest extends TestCase
{
    public function testRejectsUpdateWhenFlagIsDisabled(): void
    {
        $config = $this->createMock(UpdateConfigProvider::class);
        $config->method('isEnabled')->willReturn(false);
        $config->expects(self::never())->method('getApiKey');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Ergonode updates are disabled.');

        (new UpdateGuard($config))->authorize();
    }

    public function testRejectsMissingDedicatedApiKey(): void
    {
        $config = $this->createMock(UpdateConfigProvider::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('getApiKey')->willReturn('');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Ergonode update API key is missing.');

        (new UpdateGuard($config))->authorize();
    }

    public function testReturnsDedicatedApiKeyWhenUpdatesAreEnabled(): void
    {
        $config = $this->createMock(UpdateConfigProvider::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('getApiKey')->willReturn('dedicated-update-key');

        self::assertSame('dedicated-update-key', (new UpdateGuard($config))->authorize());
    }
}
