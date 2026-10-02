<?php

declare(strict_types=1);

namespace Vendivo\CodeDuplicates\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vendivo\CodeDuplicates\SourceScanner;

#[CoversClass(SourceScanner::class)]
final class SourceScannerTest extends TestCase
{
    public function testExcludesMagentoTestDirectoriesByDefault(): void
    {
        $root = __DIR__ . '/Fixture/project';
        $scanner = new SourceScanner($root);

        self::assertSame(
            ['Source.php'],
            array_keys($scanner->scan([$root]))
        );
        self::assertSame(
            ['Source.php', 'Test/Excluded.php'],
            array_keys($scanner->scan([$root], true))
        );
    }
}
