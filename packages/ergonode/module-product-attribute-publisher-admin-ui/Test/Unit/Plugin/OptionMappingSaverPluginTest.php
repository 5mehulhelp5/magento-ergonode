<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisherAdminUi\Test\Unit\Plugin;

use Ergonode\ProductAttribute\Api\MappingSaveNoticeCollectorInterface;
use Ergonode\ProductAttribute\Api\MappingReaderInterface;
use Ergonode\ProductAttributeAdminUi\Api\ErgonodeMetadataProviderInterface;
use Ergonode\ProductAttributeAdminUi\Model\Mapping\AttributeMappingProvider;
use Ergonode\ProductAttributeAdminUi\Model\Mapping\OptionMappingSaver;
use Ergonode\ProductAttributePublisherAdminUi\Model\ErgonodeOptionCreator;
use Ergonode\ProductAttributePublisherAdminUi\Model\PublishedMetadataVerifier;
use Ergonode\ProductAttributePublisherAdminUi\Plugin\OptionMappingSaverPlugin;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Ergonode\AttributePublisher\Model\Data\AttributeOptionState;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class OptionMappingSaverPluginTest extends TestCase
{
    public function testSynchronizesBeforeLocalSave(): void
    {
        $source = ['code' => 'option_12', 'label' => 'Blue', 'type' => 'option'];
        $prepared = ['code' => 'option_12', 'label' => 'Blue', 'pending_create' => false];
        $provider = $this->createMock(AttributeMappingProvider::class);
        $provider->expects(self::once())->method('getMappingRow')->with(8)->willReturn(
            [
            'ergonode_attribute_code' => 'color', 'magento_attribute_code' => 'magento_color',
            ]
        );
        $calls = [];
        $creator = $this->createMock(ErgonodeOptionCreator::class);
        $state = new AttributeOptionState('option_12', ['pl_PL' => 'Blue']);
        $creator->expects(self::once())->method('prepareState')
            ->with('magento_color', 'color', $source)->willReturn($state);
        $creator->expects(self::once())->method('prepareMapping')->with($state)->willReturn($prepared);
        $creator->expects(self::once())
            ->method('synchronizeFromMagento')
            ->with('color', $state, 'option_12')
            ->willReturnCallback(
                static function () use (&$calls): string {
                    $calls[] = 'remote';

                    return 'Option already exists and was linked.';
                }
            );
        $noticeCollector = $this->createMock(MappingSaveNoticeCollectorInterface::class);
        $noticeCollector->expects(self::once())
            ->method('addWarning')
            ->with('Option already exists and was linked.');
        $proceed = static function (int $mappingId, array $mappings) use (&$calls, $prepared): array {
            self::assertSame(8, $mappingId);
            self::assertSame($prepared, $mappings[0]['left']);
            $calls[] = 'local';

            return ['inserted' => 1];
        };

        $result = (new OptionMappingSaverPlugin(
            $provider,
            $creator,
            $noticeCollector,
            $this->createStub(MappingReaderInterface::class),
            $this->createStub(ErgonodeMetadataProviderInterface::class),
            $this->createStub(PublishedMetadataVerifier::class)
        ))->aroundSave(
            $this->createStub(OptionMappingSaver::class),
            $proceed,
            8,
            [['left' => ['pending_create' => true], 'right' => $source]],
            []
        );

        self::assertSame(['inserted' => 1], $result);
        self::assertSame(['remote', 'local'], $calls);
    }

    public function testRemoteFailurePreventsLocalSave(): void
    {
        $source = ['code' => 'option_12', 'label' => 'Blue'];
        $provider = $this->createStub(AttributeMappingProvider::class);
        $provider->method('getMappingRow')->willReturn([
            'ergonode_attribute_code' => 'color', 'magento_attribute_code' => 'magento_color',
        ]);
        $creator = $this->createMock(ErgonodeOptionCreator::class);
        $creator->method('prepareMapping')->willReturn($source);
        $creator->expects(self::once())
            ->method('synchronizeFromMagento')
            ->willThrowException(new LocalizedException(new Phrase('Remote option failure.')));
        $localSaveCalled = false;
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Remote option failure.');

        try {
            (new OptionMappingSaverPlugin(
                $provider,
                $creator,
                $this->createStub(MappingSaveNoticeCollectorInterface::class),
                $this->createStub(MappingReaderInterface::class),
                $this->createStub(ErgonodeMetadataProviderInterface::class),
                $this->createStub(PublishedMetadataVerifier::class)
            ))->aroundSave(
                $this->createStub(OptionMappingSaver::class),
                static function () use (&$localSaveCalled): array {
                    $localSaveCalled = true;

                    return [];
                },
                8,
                [['left' => ['pending_create' => true], 'right' => $source]],
                []
            );
        } finally {
            self::assertFalse($localSaveCalled);
        }
    }

    public function testWrapsUnexpectedRemoteFailureAndPreventsLocalSave(): void
    {
        $source = ['code' => 'option_12', 'label' => 'Blue'];
        $provider = $this->createStub(AttributeMappingProvider::class);
        $provider->method('getMappingRow')->willReturn([
            'ergonode_attribute_code' => 'color', 'magento_attribute_code' => 'magento_color',
        ]);
        $creator = $this->createStub(ErgonodeOptionCreator::class);
        $creator->method('prepareMapping')->willReturn($source);
        $creator->method('synchronizeFromMagento')->willThrowException(new RuntimeException('Transport failure.'));
        $localSaveCalled = false;
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unable to synchronize the Ergonode option before saving its mapping.');

        try {
            (new OptionMappingSaverPlugin(
                $provider,
                $creator,
                $this->createStub(MappingSaveNoticeCollectorInterface::class),
                $this->createStub(MappingReaderInterface::class),
                $this->createStub(ErgonodeMetadataProviderInterface::class),
                $this->createStub(PublishedMetadataVerifier::class)
            ))->aroundSave(
                $this->createStub(OptionMappingSaver::class),
                static function () use (&$localSaveCalled): array {
                    $localSaveCalled = true;

                    return [];
                },
                8,
                [['left' => ['pending_create' => true], 'right' => $source]],
                []
            );
        } finally {
            self::assertFalse($localSaveCalled);
        }
    }

    public function testGeneratedCodeCollisionPreventsRemoteMutation(): void
    {
        $provider = $this->createStub(AttributeMappingProvider::class);
        $provider->method('getMappingRow')->willReturn([
            'ergonode_attribute_code' => 'color', 'magento_attribute_code' => 'magento_color',
        ]);
        $creator = $this->createMock(ErgonodeOptionCreator::class);
        $creator->expects(self::exactly(2))->method('prepareMapping')->willReturn(
            [
            'code' => 'blue',
            'label' => 'Blue',
            ]
        );
        $creator->expects(self::never())->method('synchronizeFromMagento');
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('generated for more than one Magento option');

        (new OptionMappingSaverPlugin(
            $provider,
            $creator,
            $this->createStub(MappingSaveNoticeCollectorInterface::class),
            $this->createStub(MappingReaderInterface::class),
            $this->createStub(ErgonodeMetadataProviderInterface::class),
            $this->createStub(PublishedMetadataVerifier::class)
        ))->aroundSave(
            $this->createStub(OptionMappingSaver::class),
            static fn (): array => [],
            8,
            [
                [
                    'left' => ['pending_create' => true],
                    'right' => ['code' => 'option_10', 'label' => 'Blue'],
                ],
                [
                    'left' => ['pending_create' => true],
                    'right' => ['code' => 'option_11', 'label' => 'Blue!'],
                ],
            ],
            []
        );
    }
}
