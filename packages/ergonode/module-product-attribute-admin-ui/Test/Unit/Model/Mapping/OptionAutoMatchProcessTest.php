<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeAdminUi\Test\Unit\Model\Mapping;

use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\ProductAttribute\Api\MappingReaderInterface;
use Ergonode\ProductAttribute\Api\OptionAutoMatcherInterface;
use Ergonode\ProductAttribute\Model\Provider\MagentoOptionProvider;
use Ergonode\ProductAttributeAdminUi\Api\ErgonodeMetadataProviderInterface;
use Ergonode\ProductAttributeAdminUi\Model\Mapping\OptionAutoMatchProcess;
use Ergonode\ProductAttributeAdminUi\Model\Mapping\RemoteAttributeMetadataSource;
use PHPUnit\Framework\TestCase;

class OptionAutoMatchProcessTest extends TestCase
{
    public function testStoreZeroResolutionDoesNotFetchTranslations(): void
    {
        $reader = $this->createStub(MappingReaderInterface::class);
        $reader->method('getAttributeRow')->willReturn([
            'ergonode_attribute_code' => 'color', 'magento_attribute_code' => 'color',
        ]);
        $catalog = $this->createStub(ErgonodeMetadataProviderInterface::class);
        $catalog->method('getVerifiedOptions')->willReturn([['code' => 'blue', 'label' => 'Blue']]);
        $magento = $this->createStub(MagentoOptionProvider::class);
        $magento->method('getOptions')->willReturn([['code' => 'option_10', 'label' => 'Blue']]);
        $remote = $this->createMock(RemoteAttributeMetadataSource::class);
        $remote->expects(self::never())->method('getOptions');
        $remote->expects(self::never())->method('refreshOptions');
        $languages = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languages->method('getLanguageStoreMap')->willReturn([2 => 'pl_PL']);
        $matcher = $this->createMock(OptionAutoMatcherInterface::class);
        $matcher->expects(self::once())->method('suggest')->willReturn([
            'matches' => [['left' => ['code' => 'blue'], 'right' => ['code' => 'option_10']]],
            'conflicts' => [], 'unmatched' => [],
        ]);
        $process = new OptionAutoMatchProcess(
            $reader,
            $catalog,
            $magento,
            $languages,
            $matcher,
            $remote,
            $this->createStub(ConfigProvider::class)
        );

        self::assertCount(1, $process->suggest(7, ['blue'], ['option_10'])['matches']);
    }

    public function testRefreshesMissingRemoteNamesAndOnlySuggestsPairs(): void
    {
        $reader = $this->createStub(MappingReaderInterface::class);
        $reader->method('getAttributeRow')->willReturn([
            'ergonode_attribute_code' => 'color', 'magento_attribute_code' => 'color',
        ]);
        $remote = $this->createMock(RemoteAttributeMetadataSource::class);
        $remote->method('getOptions')->willReturn([]);
        $remote->expects(self::once())->method('refreshOptions')->with('color')
            ->willReturn(['imported' => 1, 'changed' => 1]);
        $config = $this->createStub(ConfigProvider::class);
        $config->method('isEnabled')->willReturn(true);
        $catalog = $this->createStub(ErgonodeMetadataProviderInterface::class);
        $catalog->method('getVerifiedOptions')->willReturn([['code' => 'blue', 'names' => ['pl_PL' => 'Niebieski']]]);
        $magento = $this->createStub(MagentoOptionProvider::class);
        $magento->method('getOptions')->willReturn([['code' => 'option_10', 'label' => 'Blue']]);
        $languages = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languages->method('getLanguageStoreMap')->willReturn([2 => 'pl_PL']);
        $matcher = $this->createMock(OptionAutoMatcherInterface::class);
        $passes = [];
        $matcher->expects(self::exactly(2))->method('suggest')
            ->willReturnCallback(static function (
                int $id,
                array $left,
                array $right,
                array $storeLanguages = []
            ) use (&$passes): array {
                self::assertSame(7, $id);
                self::assertSame('ergo', $left[0]['source']);
                self::assertSame('magento', $right[0]['source']);
                $passes[] = $storeLanguages;

                return ['matches' => [], 'conflicts' => [], 'unmatched' => ['blue']];
            });

        $process = new OptionAutoMatchProcess($reader, $catalog, $magento, $languages, $matcher, $remote, $config);

        self::assertSame(
            ['matches' => [], 'conflicts' => [], 'unmatched' => ['blue']],
            $process->suggest(7, ['blue'], ['option_10'])
        );
        self::assertSame([[], [2 => 'pl_PL']], $passes);
    }
}
