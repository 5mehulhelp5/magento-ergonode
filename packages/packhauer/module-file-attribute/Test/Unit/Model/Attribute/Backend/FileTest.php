<?php

declare(strict_types=1);

namespace PackHauer\FileAttribute\Test\Unit\Model\Attribute\Backend;

use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use PackHauer\FileAttribute\Api\FileStorageInterface;
use PackHauer\FileAttribute\Model\Attribute\Backend\File;

class FileTest extends TestCase
{
    public function testPromotesAFileUploaderValueBeforeSave(): void
    {
        $storage = $this->createMock(FileStorageInterface::class);
        $storage->expects(self::once())
            ->method('normalizePath')
            ->with('catalog/product/tmp/files/a/b/manual.pdf')
            ->willReturn('catalog/product/tmp/files/a/b/manual.pdf');
        $storage->expects(self::once())
            ->method('promoteTemporaryFile')
            ->with('catalog/product/tmp/files/a/b/manual.pdf', 'manual')
            ->willReturn('catalog/product/files/manual/a/b/manual.pdf');
        $product = new DataObject([
            'manual' => [[
                'name' => 'manual.pdf',
                'path' => 'catalog/product/tmp/files/a/b/manual.pdf',
                'tmp_name' => true,
            ]],
        ]);

        $this->backend($storage)->beforeSave($product);

        self::assertSame('catalog/product/files/manual/a/b/manual.pdf', $product->getData('manual'));
    }

    public function testKeepsAnExistingIntegrationMediaPath(): void
    {
        $storage = $this->createMock(FileStorageInterface::class);
        $storage->expects(self::once())
            ->method('normalizePath')
            ->with('catalog/product/ergonode/specification.pdf')
            ->willReturn('catalog/product/ergonode/specification.pdf');
        $product = new DataObject(['manual' => 'catalog/product/ergonode/specification.pdf']);

        $this->backend($storage)->beforeSave($product);

        self::assertSame('catalog/product/ergonode/specification.pdf', $product->getData('manual'));
    }

    public function testRejectsMoreThanOneFile(): void
    {
        $this->expectException(LocalizedException::class);
        $this->backend($this->createStub(FileStorageInterface::class))->beforeSave(new DataObject([
            'manual' => [['path' => 'one.pdf'], ['path' => 'two.pdf']],
        ]));
    }

    private function backend(FileStorageInterface $storage): File
    {
        $attribute = $this->createStub(Attribute::class);
        $attribute->method('getAttributeCode')->willReturn('manual');
        $backend = new File($storage);
        $backend->setAttribute($attribute);

        return $backend;
    }
}
