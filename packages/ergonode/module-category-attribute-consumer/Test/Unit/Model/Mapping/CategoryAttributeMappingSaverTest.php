<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Unit\Model\Mapping;

use Ergonode\CategoryAttribute\Api\AttributeMappingWriterInterface;
use Ergonode\CategoryAttribute\Api\MagentoAttributeProviderInterface;
use Ergonode\CategoryAttributeConsumer\Model\Mapping\CategoryAttributeMappingSaver;
use Ergonode\CategoryAttributeConsumer\Model\Provider\ErgonodeCategoryAttributeProvider;
use Ergonode\CategoryAttributeConsumer\Model\Provider\MagentoCategoryAttributeCreator;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class CategoryAttributeMappingSaverTest extends TestCase
{
    public function testInvalidMappingCannotCreateAMagentoDefinition(): void
    {
        $source = $this->createStub(ErgonodeCategoryAttributeProvider::class);
        $source->method('getAttribute')->willReturn(['code' => 'source', 'type' => 'text']);
        $creator = $this->createMock(MagentoCategoryAttributeCreator::class);
        $creator->method('previewFromErgonodeAttribute')->willReturn([
            'code' => 'target', 'type' => 'text', 'created' => true,
        ]);
        $creator->expects(self::never())->method('createFromErgonodeAttribute');
        $writer = $this->createMock(AttributeMappingWriterInterface::class);
        $writer->expects(self::once())->method('validate')
            ->willThrowException(new LocalizedException(__('Invalid mapping')));
        $writer->expects(self::never())->method('save');
        $saver = new CategoryAttributeMappingSaver(
            $source,
            $this->createStub(MagentoAttributeProviderInterface::class),
            $creator,
            $writer
        );

        $this->expectException(LocalizedException::class);
        $saver->save([['left' => ['code' => 'source'], 'right' => [
            'code' => 'pending_target', 'pending_create' => true,
        ]]], []);
    }

    public function testConsumerResolvesMetadataBeforeSavingAndRetainsOperationOrder(): void
    {
        $sourceAttribute = ['code' => 'source', 'type' => 'text', 'label' => 'Source'];
        $targetAttribute = ['code' => 'target', 'type' => 'text', 'created' => true];
        $source = $this->createStub(ErgonodeCategoryAttributeProvider::class);
        $source->method('getAttribute')->willReturn($sourceAttribute);
        $creator = $this->createMock(MagentoCategoryAttributeCreator::class);
        $creator->method('previewFromErgonodeAttribute')->willReturn($targetAttribute);
        $order = [];
        $creator->expects(self::once())->method('createFromErgonodeAttribute')->with($sourceAttribute)
            ->willReturnCallback(static function () use (&$order, $targetAttribute): array {
                $order[] = 'create';

                return $targetAttribute;
            });
        $resolved = [['left' => $sourceAttribute, 'right' => $targetAttribute]];
        $writer = $this->createMock(AttributeMappingWriterInterface::class);
        $writer->expects(self::once())->method('validate')->with($resolved)
            ->willReturnCallback(static function () use (&$order): void {
                $order[] = 'validate';
            });
        $writer->expects(self::once())->method('save')->with($resolved, [])
            ->willReturnCallback(static function () use (&$order): array {
                $order[] = 'save';

                return ['inserted' => 1, 'updated' => 0, 'deleted' => 0, 'unchanged' => 0];
            });
        $stats = (new CategoryAttributeMappingSaver(
            $source,
            $this->createStub(MagentoAttributeProviderInterface::class),
            $creator,
            $writer
        ))->save([['left' => ['code' => 'source'], 'right' => [
            'code' => 'pending_target', 'pending_create' => true,
        ]]], []);

        self::assertSame(['validate', 'create', 'save'], $order);
        self::assertSame(1, $stats['inserted']);
    }
}
