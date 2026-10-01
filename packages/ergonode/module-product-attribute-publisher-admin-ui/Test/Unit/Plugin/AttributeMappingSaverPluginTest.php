<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisherAdminUi\Test\Unit\Plugin;

use Ergonode\ProductAttribute\Api\MappingSaveNoticeCollectorInterface;
use Ergonode\ProductAttribute\Api\MappingReaderInterface;
use Ergonode\ProductAttributeAdminUi\Api\ErgonodeMetadataProviderInterface;
use Ergonode\ProductAttributeAdminUi\Model\Mapping\AttributeMappingSaver;
use Ergonode\ProductAttributePublisherAdminUi\Model\ErgonodeAttributeCreator;
use Ergonode\ProductAttributePublisherAdminUi\Model\PublishedMetadataVerifier;
use Ergonode\ProductAttributePublisherAdminUi\Plugin\AttributeMappingSaverPlugin;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use PHPUnit\Framework\TestCase;

class AttributeMappingSaverPluginTest extends TestCase
{
    public function testSynchronizesBeforeLocalSave(): void
    {
        $source = ['code' => 'material', 'label' => 'Material', 'type' => 'text'];
        $mappings = [[
            'left' => ['code' => 'material', 'type' => 'textarea', 'pending_create' => true],
            'right' => $source,
        ]];
        $adapted = $source + ['target_type' => 'textarea'];
        $mapping = ['code' => 'material', 'type' => 'textarea', 'pending_create' => false];
        $calls = [];
        $creator = $this->createMock(ErgonodeAttributeCreator::class);
        $creator->expects(self::once())->method('prepareMapping')->with($adapted)->willReturn($mapping);
        $creator->expects(self::once())->method('synchronizeFromMagento')->with($adapted)
            ->willReturnCallback(
                static function () use (&$calls): string {
                    $calls[] = 'remote';

                    return 'Attribute already exists and was linked.';
                }
            );
        $noticeCollector = $this->createMock(MappingSaveNoticeCollectorInterface::class);
        $noticeCollector->expects(self::once())
            ->method('addWarning')
            ->with('Attribute already exists and was linked.');
        $proceed = static function (array $savedMappings) use (&$calls, $mapping): array {
            self::assertSame($mapping, $savedMappings[0]['left']);
            $calls[] = 'local';
            return ['updated' => 1];
        };

        $result = (new AttributeMappingSaverPlugin(
            $creator,
            $noticeCollector,
            $this->createStub(MappingReaderInterface::class),
            $this->createStub(ErgonodeMetadataProviderInterface::class),
            $this->createStub(PublishedMetadataVerifier::class)
        ))->aroundSave(
            $this->createStub(AttributeMappingSaver::class),
            $proceed,
            $mappings,
            []
        );

        self::assertSame(['updated' => 1], $result);
        self::assertSame(['remote', 'local'], $calls);
    }

    public function testRemoteFailurePreventsLocalSave(): void
    {
        $source = ['code' => 'material', 'label' => 'Material', 'type' => 'text'];
        $creator = $this->createMock(ErgonodeAttributeCreator::class);
        $creator->method('prepareMapping')->willReturn($source);
        $creator->expects(self::once())
            ->method('synchronizeFromMagento')
            ->willThrowException(new LocalizedException(new Phrase('Remote failure.')));
        $localSaveCalled = false;
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Remote failure.');

        try {
            (new AttributeMappingSaverPlugin(
                $creator,
                $this->createStub(MappingSaveNoticeCollectorInterface::class),
                $this->createStub(MappingReaderInterface::class),
                $this->createStub(ErgonodeMetadataProviderInterface::class),
                $this->createStub(PublishedMetadataVerifier::class)
            ))->aroundSave(
                $this->createStub(AttributeMappingSaver::class),
                static function () use (&$localSaveCalled): array {
                    $localSaveCalled = true;

                    return [];
                },
                [['left' => ['pending_create' => true], 'right' => $source]],
                []
            );
        } finally {
            self::assertFalse($localSaveCalled);
        }
    }
}
