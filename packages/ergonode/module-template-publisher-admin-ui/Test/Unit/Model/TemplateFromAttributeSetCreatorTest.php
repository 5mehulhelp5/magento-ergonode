<?php

declare(strict_types=1);

namespace Ergonode\TemplatePublisherAdminUi\Test\Unit\Model;

use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\Template\Api\ProductAttributeSetProviderInterface;
use Ergonode\Template\Api\PublishedTemplateRecorderInterface;
use Ergonode\TemplatePublisher\Api\TemplateCreatorInterface;
use Ergonode\TemplatePublisherAdminUi\Model\TemplateFromAttributeSetCreator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TemplateFromAttributeSetCreatorTest extends TestCase
{
    public function testRecordsPublishedTemplateWithoutImporting(): void
    {
        $attributeSetProvider = $this->createStub(ProductAttributeSetProviderInterface::class);
        $attributeSetProvider->method('getProductAttributeSets')->willReturn([
            ['id' => 4, 'name' => 'Default'],
            ['id' => 12, 'name' => 'Summer Collection'],
        ]);
        $creator = $this->createMock(TemplateCreatorInterface::class);
        $creator->expects(self::once())->method('create')->with(
            'summer_collection',
            ['pl_PL' => 'Summer Collection']
        );
        $languages = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languages->method('requireAdminLanguageCode')->willReturn('pl_PL');
        $synchronizer = $this->createMock(PublishedTemplateRecorderInterface::class);
        $synchronizer->expects(self::once())->method('record')
            ->with('summer_collection', ['pl_PL' => 'Summer Collection']);

        $service = new TemplateFromAttributeSetCreator($attributeSetProvider, $creator, $languages, $synchronizer);

        self::assertSame(
            ['code' => 'summer_collection', 'name' => 'Summer Collection'],
            $service->create('summer_collection', 12)
        );
    }

    public function testPropagatesRecorderFailureAfterRemoteCreation(): void
    {
        $attributeSetProvider = $this->createStub(ProductAttributeSetProviderInterface::class);
        $attributeSetProvider->method('getProductAttributeSets')->willReturn([
            ['id' => 12, 'name' => 'Summer Collection'],
        ]);
        $creator = $this->createMock(TemplateCreatorInterface::class);
        $creator->expects(self::once())->method('create')->with(
            'summer_collection',
            ['pl_PL' => 'Summer Collection']
        );
        $languages = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languages->method('requireAdminLanguageCode')->willReturn('pl_PL');
        $recorder = $this->createMock(PublishedTemplateRecorderInterface::class);
        $recorder->expects(self::once())->method('record')
            ->with('summer_collection', ['pl_PL' => 'Summer Collection'])
            ->willThrowException(new RuntimeException('Recorder failed after remote success.'));

        $service = new TemplateFromAttributeSetCreator($attributeSetProvider, $creator, $languages, $recorder);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Recorder failed after remote success.');

        $service->create('summer_collection', 12);
    }
}
