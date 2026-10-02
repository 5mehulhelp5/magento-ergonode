<?php

declare(strict_types=1);

namespace Ergonode\ProductMediaConsumer\Test\Unit\Model\Magento;

use Ergonode\ProductMediaConsumer\Model\Magento\AsynchronousFileAttributeMapping;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AsynchronousFileAttributeMappingTest extends TestCase
{
    /** @return array<string, array{string, string, bool}> */
    public static function mappingProvider(): array
    {
        return [
            'file to file' => ['file', 'file', true],
            'normalized file to file' => [' FILE ', 'File', true],
            'file to text' => ['file', 'text', false],
            'image to file' => ['image', 'file', false],
        ];
    }

    #[DataProvider('mappingProvider')]
    public function testClaimsOnlyFileToFileMapping(string $source, string $target, bool $expected): void
    {
        self::assertSame($expected, (new AsynchronousFileAttributeMapping())->supports([
            'ergonode_type' => $source,
            'magento_type' => $target,
        ]));
    }
}
