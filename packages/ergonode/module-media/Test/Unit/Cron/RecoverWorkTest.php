<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Unit\Cron;

use Ergonode\Media\Cron\RecoverWork;
use PHPUnit\Framework\TestCase;

class RecoverWorkTest extends TestCase
{
    public function testNoRecoveryJobOrRetrySettingRemainsConfigured(): void
    {
        $module = dirname(__DIR__, 3);
        self::assertStringNotContainsString('ergonode_media_recovery', file_get_contents($module . '/etc/crontab.xml'));
        self::assertStringNotContainsString('maximum_attempts', file_get_contents($module . '/etc/config.xml'));
        // A cron row scheduled before deployment cannot resubmit any work either.
        self::assertNull((new RecoverWork())->execute());
    }
}
