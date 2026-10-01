<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisherAdminUi\Test\Unit\Model;

use Ergonode\AttributePublisher\Model\Data\AttributeOptionState;
use Ergonode\AttributePublisherAdminUi\Model\ExistingSynchronizationNoticeResolver;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\ProductAttributePublisher\Api\OptionDefinitionPublisherInterface;
use Ergonode\ProductAttributePublisherAdminUi\Model\ErgonodeOptionCreator;
use Ergonode\ProductAttributePublisherAdminUi\Model\PublishedMetadataVerifier;
use Ergonode\Publisher\Api\Data\SynchronizationResultInterface;
use PHPUnit\Framework\TestCase;

class ErgonodeOptionCreatorTest extends TestCase
{
    public function testMappingAndSinglePublicationUseTheSameTranslatedState(): void
    {
        $state = new AttributeOptionState('disabled', ['en_GB' => 'No', 'pl_PL' => 'Nie']);
        $source = ['code' => 'option_2', 'label' => 'Disabled'];
        $result = $this->createStub(SynchronizationResultInterface::class);
        $result->method('isSuccessful')->willReturn(true);
        $publisher = $this->createMock(OptionDefinitionPublisherInterface::class);
        $publisher->expects(self::once())->method('prepareState')
            ->with('status', 'product_status', $source)->willReturn($state);
        $publisher->expects(self::once())->method('publish')->with('product_status', $state)->willReturn($result);
        $metadataVerifier = $this->createMock(PublishedMetadataVerifier::class);
        $metadataVerifier->expects(self::once())->method('verifyAttributes')->with(['product_status']);
        $languages = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languages->method('requireAdminLanguageCode')->willReturn('pl_PL');
        $creator = new ErgonodeOptionCreator(
            $publisher,
            $metadataVerifier,
            $languages,
            new ExistingSynchronizationNoticeResolver()
        );

        $prepared = $creator->prepareState('status', 'product_status', $source);
        self::assertSame('Nie', $creator->prepareMapping($prepared)['label']);
        self::assertSame('disabled', $creator->prepareMapping($prepared)['code']);
        self::assertNull($creator->synchronizeFromMagento('product_status', $prepared, 'option_2'));
    }

    public function testReturnsWarningWhenExistingOptionIsLinked(): void
    {
        $state = new AttributeOptionState('no', ['pl_PL' => 'Nie']);
        $result = $this->createStub(SynchronizationResultInterface::class);
        $publisher = $this->createStub(OptionDefinitionPublisherInterface::class);
        $publisher->method('publish')->willReturn($result);
        $notice = $this->createMock(ExistingSynchronizationNoticeResolver::class);
        $notice->expects(self::once())->method('wasExisting')->with($result, 'option_add:')->willReturn(true);
        $metadataVerifier = $this->createMock(PublishedMetadataVerifier::class);
        $metadataVerifier->expects(self::once())->method('verifyAttributes')->with(['enabled']);
        $creator = new ErgonodeOptionCreator(
            $publisher,
            $metadataVerifier,
            $this->createStub(LanguageStoreMappingProviderInterface::class),
            $notice
        );

        self::assertSame(
            'Option "no" already exists in Ergonode and has been linked to Magento option "option_0".',
            $creator->synchronizeFromMagento('enabled', $state, 'option_0')
        );
    }
}
