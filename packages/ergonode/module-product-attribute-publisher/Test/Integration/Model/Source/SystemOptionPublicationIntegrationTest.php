<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisher\Test\Integration\Model\Source;

use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\ProductAttributePublisher\Api\OptionDefinitionPublisherInterface;
use Ergonode\ProductAttributePublisher\Model\Source\OptionSourceStateBuilder;
use Ergonode\ProductAttributePublisher\Model\Source\SystemOptionDefinition;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean;
use PHPUnit\Framework\TestCase;

class SystemOptionPublicationIntegrationTest extends TestCase
{
    public function testMagentoSystemSourcesPrepareTranslatedDefinitionsThroughDi(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $languages = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languages->method('getLanguageCodes')->willReturn(['pl_PL', 'en_GB', 'ja_JP']);
        $systemDefinition = $objectManager->create(SystemOptionDefinition::class, [
            'languageMappingProvider' => $languages,
        ]);
        $builder = $objectManager->create(OptionSourceStateBuilder::class, [
            'systemOptionDefinition' => $systemDefinition,
        ]);
        $publisher = $objectManager->create(OptionDefinitionPublisherInterface::class, [
            'optionStateBuilder' => $builder,
        ]);
        $boolean = $objectManager->create(Attribute::class);
        $boolean->setAttributeCode('test_boolean');
        $boolean->setFrontendInput('boolean');
        $boolean->setSourceModel(Boolean::class);
        $no = $builder->build($boolean, 'test_boolean', [0])[0];
        self::assertSame('no', $no->getCode());
        self::assertSame('Nie', $no->getNames()['pl_PL']);

        $status = $publisher->prepareState('status', 'test_status', ['code' => 'option_2']);
        $visibility = $publisher->prepareState('visibility', 'test_visibility', ['code' => 'option_4']);

        self::assertSame('disabled', $status->getCode());
        self::assertSame(['en_GB' => 'No', 'ja_JP' => 'No', 'pl_PL' => 'Nie'], $status->getNames());
        self::assertSame('catalog_search', $visibility->getCode());
        self::assertSame('Katalog, wyszukiwanie', $visibility->getNames()['pl_PL']);
        self::assertSame('Catalog, Search', $visibility->getNames()['ja_JP']);
    }
}
