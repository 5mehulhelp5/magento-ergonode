<?php

declare(strict_types=1);

namespace Vendivo\CodeDuplicates\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vendivo\CodeDuplicates\ConsoleApplication;

#[CoversClass(ConsoleApplication::class)]
final class ConsoleApplicationTest extends TestCase
{
    public function testReturnsJsonAndFailureStatusForDetectedDuplicates(): void
    {
        $projectRoot = realpath(__DIR__ . '/../../..');
        self::assertIsString($projectRoot);
        $application = new ConsoleApplication($projectRoot, ['src']);

        ob_start();
        try {
            $status = $application->run([
                '--min-score=8',
                '--format=json',
                '--fail-on-duplicates',
                __DIR__ . '/Fixture/duplicates',
            ]);
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertSame(1, $status);
        self::assertIsString($output);
        /** @var array{files: int, duplicateGroups: int} $report */
        $report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(2, $report['files']);
        self::assertSame(1, $report['duplicateGroups']);
    }

    public function testReturnsConciseAgentErrorAndStatusTwo(): void
    {
        $projectRoot = realpath(__DIR__ . '/../../..');
        self::assertIsString($projectRoot);
        $application = new ConsoleApplication($projectRoot, ['src']);

        ob_start();
        try {
            $status = $application->run(['--format=ai', 'missing-path']);
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertSame(2, $status);
        self::assertIsString($output);
        self::assertStringStartsWith('CODE_DUPLICATES status=error message=', $output);
        self::assertStringNotContainsString("\n\n", $output);
    }
}
