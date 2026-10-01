<?php

declare(strict_types=1);

namespace Ergonode\LanguageAdminUi\Test\Unit\Block\Adminhtml\Language;

use Ergonode\Language\Api\LanguageMappingStateProviderInterface;
use Ergonode\Language\Model\Data\MappingStateDto;
use Ergonode\LanguageAdminUi\Block\Adminhtml\Language\Mapping;
use Ergonode\LanguageAdminUi\Model\LocaleLabelProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Magento\Framework\AuthorizationInterface;
use PHPUnit\Framework\Attributes\DataProvider;

class MappingTest extends TestCase
{
    #[DataProvider('permissions')]
    public function testSaveAndRefreshPermissionsAreIndependent(bool $save, bool $refresh): void
    {
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->expects(self::exactly(2))->method('isAllowed')->willReturnMap([
            ['Ergonode_Language::language_mapping_save', null, $save],
            ['Ergonode_Language::language_mapping_refresh', null, $refresh],
        ]);
        $block = (new ReflectionClass(Mapping::class))->newInstanceWithoutConstructor();
        $this->setPrivateProperty($block, '_authorization', $authorization);
        self::assertSame($save, $block->canSaveMappings());
        self::assertSame($refresh, $block->canRefreshLanguages());
    }

    /** @return array<string, array{bool, bool}> */
    public static function permissions(): array
    {
        return ['viewer' => [false, false], 'editor' => [true, false], 'refresh only' => [false, true]];
    }

    public function testSourceLanguagesComeOnlyFromCurrentSnapshot(): void
    {
        $stateProvider = $this->createMock(LanguageMappingStateProviderInterface::class);
        $stateProvider->expects(self::once())->method('getState')->willReturn(
            new MappingStateDto(['pl_PL'], [], [], ['pl_PL' => true], [], 'revision')
        );

        $localeLabelProvider = $this->createMock(LocaleLabelProvider::class);
        $localeLabelProvider->expects(self::once())
            ->method('getLabel')
            ->with('pl_PL')
            ->willReturn('Polish (Poland)');

        $block = (new ReflectionClass(Mapping::class))->newInstanceWithoutConstructor();
        $this->setPrivateProperty($block, 'stateProvider', $stateProvider);
        $this->setPrivateProperty($block, 'localeLabelProvider', $localeLabelProvider);

        self::assertSame([
            [
                'label' => 'Polish (Poland)',
                'code' => 'pl_PL',
                'active' => true,
            ],
        ], $block->getErgonodeLanguages());
        self::assertSame([], $block->getMappings());
    }

    #[DataProvider('mappingAvailability')]
    public function testMappingRetainsItsPairButReportsAvailability(
        bool $present,
        bool $languageActive,
        bool $storeActive,
        bool $expected
    ): void {
        $store = ['id' => 0, 'code' => 'admin', 'name' => 'Default', 'locale' => 'pl_PL',
            'website' => 'Global', 'group' => 'All'];
        $state = new MappingStateDto($present ? ['pl_PL'] : [], [[
            'mapping_id' => 1, 'language_code' => 'pl_PL', 'store_id' => 0, 'sort_order' => 0, 'is_manual' => 1,
        ]], [0 => $store], ['pl_PL' => $languageActive], [0 => $storeActive], 'revision');
        $provider = $this->createStub(LanguageMappingStateProviderInterface::class);
        $provider->method('getState')->willReturn($state);
        $labels = $this->createStub(LocaleLabelProvider::class);
        $labels->method('getLabel')->willReturn('Polish');
        $block = (new ReflectionClass(Mapping::class))->newInstanceWithoutConstructor();
        $this->setPrivateProperty($block, 'stateProvider', $provider);
        $this->setPrivateProperty($block, 'localeLabelProvider', $labels);

        self::assertSame([[
            'active' => $expected,
            'left' => ['label' => 'Polish', 'code' => 'pl_PL'],
            'right' => $store,
        ]], $block->getMappings());
    }

    /** @return array<string, array{bool, bool, bool, bool}> */
    public static function mappingAvailability(): array
    {
        return [
            'active pair' => [true, true, true, true],
            'removed language' => [false, true, true, false],
            'excluded language' => [true, false, true, false],
            'excluded store' => [true, true, false, false],
        ];
    }

    private function setPrivateProperty(Mapping $block, string $property, object $value): void
    {
        (new ReflectionClass(Mapping::class))->getProperty($property)->setValue($block, $value);
    }
}
