<?php

declare(strict_types=1);

namespace PackHauer\FileAttribute\Test\Unit\Model\Attribute;

use PHPUnit\Framework\TestCase;
use PackHauer\FileAttribute\Model\Attribute\Backend\File;
use PackHauer\FileAttribute\Model\Attribute\FilePropertyMapper;

class FilePropertyMapperTest extends TestCase
{
    public function testOwnsVarcharStorageAndBackendForFileInput(): void
    {
        $mapper = new FilePropertyMapper();

        self::assertSame(
            ['backend_model' => File::class, 'backend_type' => 'varchar'],
            $mapper->map(['input' => 'file'], 4)
        );
        self::assertSame([], $mapper->map(['input' => 'text'], 4));
    }
}
