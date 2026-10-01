<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisherAdminUi\Test\Unit\Model;

use Ergonode\AttributePublisher\Api\AttributeBatchStateLoaderInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeStateInterface;
use Ergonode\CategoryAttributePublisher\Api\WriteScopeCategoryAttributeCodeLoaderInterface;
use Ergonode\CategoryAttributePublisherAdminUi\Model\Mapping\SourceMetadata;
use Ergonode\Core\Model\Config\ConfigProvider;
use PHPUnit\Framework\TestCase;
use Magento\Framework\Exception\LocalizedException;

class SourceMetadataTest extends TestCase
{
    public function testDisabledPublicationDoesNotReadRemoteData(): void
    {
        $config = $this->createStub(ConfigProvider::class);
        $config->method('allowsWrites')->willReturn(false);
        $registry = $this->createMock(WriteScopeCategoryAttributeCodeLoaderInterface::class);
        $registry->expects(self::never())->method('loadWriteScope');
        $loader = $this->createMock(AttributeBatchStateLoaderInterface::class);
        $loader->expects(self::never())->method('loadBatch');
        $source = new SourceMetadata($registry, $loader, $config);
        self::assertSame([], $source->getAttributes());
        self::assertSame([], $source->getOptions('color'));
    }

    public function testPublisherAloneProvidesCategoryMetadataAndRefreshesAfterCreation(): void
    {
        $config = $this->createStub(ConfigProvider::class);
        $config->method('allowsWrites')->willReturn(true);
        $registry = $this->createMock(WriteScopeCategoryAttributeCodeLoaderInterface::class);
        $registry->expects(self::exactly(2))->method('loadWriteScope')->willReturn(['color']);
        $state = $this->createStub(AttributeStateInterface::class);
        $state->method('getCode')->willReturn('color');
        $state->method('getType')->willReturn('multi_select');
        $state->method('getScope')->willReturn('GLOBAL');
        $state->method('getNames')->willReturn(['pl_PL' => 'Kolor']);
        $state->method('getParameters')->willReturn([]);
        $state->method('getOptions')->willReturn([]);
        $loader = $this->createMock(AttributeBatchStateLoaderInterface::class);
        $loader->expects(self::exactly(2))->method('loadBatch')->with(['color'])->willReturn(['color' => $state]);
        $source = new SourceMetadata($registry, $loader, $config);
        self::assertSame('multiselect', $source->getAttributes()[0]['type']);
        self::assertSame('Kolor', $source->getAttributes()[0]['label']);
        self::assertSame([], $source->getOptions('color'));
        $source->reset();
        self::assertSame('color', $source->getAttributes()[0]['code']);
    }
    public function testMissingDefinitionsAreSkippedAndFailureDoesNotPopulateCache(): void
    {
        $config = $this->createStub(ConfigProvider::class);
        $config->method('allowsWrites')->willReturn(true);
        $registry = $this->createMock(WriteScopeCategoryAttributeCodeLoaderInterface::class);
        $registry->expects(self::exactly(2))->method('loadWriteScope')->willReturn(['missing']);
        $loader = $this->createMock(AttributeBatchStateLoaderInterface::class);
        $calls = 0;
        $loader->expects(self::exactly(2))->method('loadBatch')->with(['missing'])->willReturnCallback(
            static function () use (&$calls): array {
                if (++$calls === 1) {
                    throw new LocalizedException(__('Incomplete response.'));
                }
                return ['missing' => null];
            }
        );
        $source = new SourceMetadata($registry, $loader, $config);
        try {
            $source->getAttributes();
            self::fail('Incomplete metadata must propagate.');
        } catch (LocalizedException $exception) {
            self::assertSame('Incomplete response.', $exception->getMessage());
        }
        self::assertSame([], $source->getAttributes());
        self::assertSame([], $source->getOptions('missing'));
    }
}
