<?php

declare(strict_types=1);

namespace PackHauer\FileAttribute\Test\Unit\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Filesystem\Driver\File\Mime;
use PHPUnit\Framework\TestCase;
use PackHauer\FileAttribute\Model\FileUploadPolicy;

class FileUploadPolicyTest extends TestCase
{
    public function testAcceptsANonEmptyPdfWithinTheLimit(): void
    {
        $driver = $this->createStub(File::class);
        $driver->method('stat')->willReturn(['size' => 1024]);
        $mime = $this->createStub(Mime::class);
        $mime->method('getMimeType')->willReturn('application/pdf');

        (new FileUploadPolicy($driver, $mime))->validateUploadedFile('/tmp/manual.pdf');

        self::assertTrue(true);
    }

    public function testRejectsActiveWebContent(): void
    {
        $driver = $this->createStub(File::class);
        $driver->method('stat')->willReturn(['size' => 1024]);
        $mime = $this->createStub(Mime::class);
        $mime->method('getMimeType')->willReturn('text/html');

        $this->expectException(LocalizedException::class);
        (new FileUploadPolicy($driver, $mime))->validateUploadedFile('/tmp/manual.pdf');
    }
}
