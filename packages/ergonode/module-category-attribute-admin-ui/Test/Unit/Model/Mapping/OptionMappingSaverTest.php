<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeAdminUi\Test\Unit\Model\Mapping;

use Ergonode\CategoryAttribute\Api\MagentoOptionProviderInterface;
use Ergonode\CategoryAttribute\Api\MappingReaderInterface;
use Ergonode\CategoryAttribute\Api\OptionMappingWriterInterface;
use Ergonode\CategoryAttributeAdminUi\Model\Mapping\OptionMappingSaver;
use Ergonode\CategoryAttributeAdminUi\Model\Mapping\SaveContext;
use Ergonode\CategoryAttributeAdminUi\Model\Mapping\SourceMetadata;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class OptionMappingSaverTest extends TestCase
{
    public function testCardsAreResolvedFromTrustedMetadata(): void
    {
        $sourceOption = ['code' => 'blue', 'label' => 'Blue', 'type' => 'option'];
        $targetOption = ['code' => 'option_10', 'label' => 'Navy', 'type' => 'option'];
        $writer = $this->createMock(OptionMappingWriterInterface::class);
        $writer->expects(self::once())->method('save')->with(7, [[
            'left' => $sourceOption,
            'right' => $targetOption,
        ]], [])->willReturn(['inserted' => 1]);

        $result = $this->saver([$sourceOption], [$targetOption], $writer)->save(7, [[
            'left' => ['code' => 'blue', 'label' => 'Forged'],
            'right' => ['code' => 'option_10', 'label' => 'Forged'],
        ]], []);

        self::assertSame(['inserted' => 1], $result);
    }

    public function testMissingSourceOptionIsRejected(): void
    {
        $writer = $this->createMock(OptionMappingWriterInterface::class);
        $writer->expects(self::never())->method('save');
        $saver = $this->saver([], [['code' => 'option_10']], $writer);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('source_missing');

        $saver->save(7, [[
            'left' => ['code' => 'source_missing'],
            'right' => ['code' => 'option_10'],
        ]], []);
    }

    public function testMagentoOptionFromAnotherAttributeIsRejected(): void
    {
        $writer = $this->createMock(OptionMappingWriterInterface::class);
        $writer->expects(self::never())->method('save');
        $saver = $this->saver([['code' => 'blue']], [['code' => 'option_10']], $writer);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('option_11');

        $saver->save(7, [[
            'left' => ['code' => 'blue'],
            'right' => ['code' => 'option_11'],
        ]], []);
    }

    /**
     * @param array<int, array<string, mixed>> $sourceOptions
     * @param array<int, array<string, mixed>> $targetOptions
     */
    private function saver(
        array $sourceOptions,
        array $targetOptions,
        OptionMappingWriterInterface $writer
    ): OptionMappingSaver {
        $source = $this->createMock(SourceMetadata::class);
        $source->method('getOptions')->with('source_color')->willReturn($sourceOptions);
        $target = $this->createMock(MagentoOptionProviderInterface::class);
        $target->method('getOptions')->with('category_color')->willReturn($targetOptions);
        $reader = $this->createMock(MappingReaderInterface::class);
        $reader->method('getAttributeRow')->with(7)->willReturn([
            'status' => 'complete',
            'ergonode_attribute_code' => 'source_color',
            'magento_attribute_code' => 'category_color',
        ]);

        return new OptionMappingSaver($source, $target, $reader, $writer, new SaveContext());
    }
}
