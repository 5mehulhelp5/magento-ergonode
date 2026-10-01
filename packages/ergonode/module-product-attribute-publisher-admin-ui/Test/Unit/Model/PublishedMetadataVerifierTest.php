<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisherAdminUi\Test\Unit\Model;

use Ergonode\AttributePublisher\Api\AttributeBatchStateLoaderInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeStateInterface;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\ProductAttributeAdminUi\Api\VerifiedErgonodeMetadataRecorderInterface;
use Ergonode\ProductAttributePublisherAdminUi\Model\PublishedMetadataVerifier;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class PublishedMetadataVerifierTest extends TestCase
{
    public function testWriteScopeDefinitionIsRecordedForNeutralSave(): void
    {
        $state = $this->createStub(AttributeStateInterface::class);
        $state->method('getCode')->willReturn('color');
        $state->method('getNames')->willReturn(['pl_PL' => 'Kolor']);
        $state->method('getType')->willReturn('select');
        $state->method('getScope')->willReturn('global');
        $loader = $this->createMock(AttributeBatchStateLoaderInterface::class);
        $loader->expects(self::once())->method('loadBatch')->with(['color'])->willReturn(['color' => $state]);
        $recorder = $this->createMock(VerifiedErgonodeMetadataRecorderInterface::class);
        $recorder->expects(self::once())->method('recordAttribute')->with(self::callback(
            static fn (array $attribute): bool => $attribute['code'] === 'color'
                && $attribute['label'] === 'Kolor'
                && $attribute['active'] === true
        ));
        $language = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $language->method('getAdminLanguageCode')->willReturn('pl_PL');

        (new PublishedMetadataVerifier($loader, $recorder, $language))->verifyAttributes(['color']);
    }

    public function testMissingWriteScopeDefinitionStopsSave(): void
    {
        $loader = $this->createStub(AttributeBatchStateLoaderInterface::class);
        $loader->method('loadBatch')->willReturn(['missing' => null]);
        $recorder = $this->createMock(VerifiedErgonodeMetadataRecorderInterface::class);
        $recorder->expects(self::never())->method('recordAttribute');

        $this->expectException(LocalizedException::class);
        (new PublishedMetadataVerifier(
            $loader,
            $recorder,
            $this->createStub(LanguageStoreMappingProviderInterface::class)
        ))->verifyAttributes(['missing']);
    }
}
