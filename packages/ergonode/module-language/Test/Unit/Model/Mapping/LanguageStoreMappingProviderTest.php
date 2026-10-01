<?php

declare(strict_types=1);

namespace Ergonode\Language\Test\Unit\Model\Mapping;

use Ergonode\Language\Api\LanguageMappingStateProviderInterface;
use Ergonode\Language\Exception\AdminLanguageMappingRequiredException;
use Ergonode\Language\Exception\NoActiveLanguageMappingException;
use Ergonode\Language\Model\Mapping\LanguageStoreMappingProvider;
use Ergonode\Language\Model\Mapping\MappingCache;
use Ergonode\Language\Model\Data\MappingStateDto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LanguageStoreMappingProviderTest extends TestCase
{
    public function testRejectsTransferWhenNoActiveCompleteMappingExists(): void
    {
        $this->expectException(NoActiveLanguageMappingException::class);

        $this->providerWithRows([])->getLanguageStoreMap();
    }

    public function testReturnsOnlyCompleteCurrentlyAvailableMappingsIncludingAdminScope(): void
    {
        $provider = $this->providerWithRows([
            $this->row(1, 2, 'pl_PL', 0),
            $this->row(2, 3, null),
            $this->row(3, 4, 'de_DE'),
            $this->row(4, 0, 'en_GB'),
            $this->row(5, 99, 'en_GB'),
            $this->row(6, 4, 'missing_LANGUAGE'),
        ]);

        self::assertSame([2 => 'pl_PL', 4 => 'de_DE', 0 => 'en_GB'], $provider->getLanguageStoreMap());
        self::assertSame('en_GB', $provider->getAdminLanguageCode());
        self::assertSame(['pl_PL', 'de_DE', 'en_GB'], $provider->getLanguageCodes());
    }

    public function testAdminLanguageIsOptionalWhenAnotherActiveMappingExists(): void
    {
        $provider = $this->providerWithRows([$this->row(1, 4, 'de_DE')]);

        self::assertNull($provider->getAdminLanguageCode());
        self::assertSame([4 => 'de_DE'], $provider->getLanguageStoreMap());

        $this->expectException(AdminLanguageMappingRequiredException::class);
        $provider->requireAdminLanguageCode();
    }

    public function testExcludesInactiveLanguagesAndStoreViews(): void
    {
        $provider = $this->providerWithRows([
            $this->row(1, 0, 'en_GB'),
            $this->row(2, 2, 'pl_PL'),
            $this->row(3, 4, 'de_DE'),
        ], ['pl_PL' => false], [4 => false]);

        self::assertSame([0 => 'en_GB'], $provider->getLanguageStoreMap());
        self::assertNull($provider->getLanguageForStoreId(2));
        self::assertNull($provider->getLanguageForStoreId(4));
        self::assertSame(['en_GB' => [0]], $provider->getStoreIdsByLanguageCode());
    }

    /**
     * @param array<string, bool> $languages
     * @param array<int, bool> $stores
     */
    #[DataProvider('inactiveAdminMapping')]
    public function testInactiveAdminMappingBlocksRequiredScope(array $languages, array $stores): void
    {
        $provider = $this->providerWithRows([
            $this->row(1, 0, 'en_GB'),
            $this->row(2, 2, 'pl_PL'),
        ], $languages, $stores);

        self::assertSame([2 => 'pl_PL'], $provider->getLanguageStoreMap());
        self::assertNull($provider->getAdminLanguageCode());
        $this->expectException(AdminLanguageMappingRequiredException::class);
        $provider->requireAdminLanguageCode();
    }

    /**
     * @param array<string, bool> $languages
     * @param array<int, bool> $stores
     */
    #[DataProvider('inactiveAdminMapping')]
    public function testDisablingLastMappingBlocksTransfer(array $languages, array $stores): void
    {
        $provider = $this->providerWithRows([$this->row(1, 0, 'en_GB')], $languages, $stores);

        self::assertNull($provider->getAdminLanguageCode());
        $this->expectException(NoActiveLanguageMappingException::class);
        $provider->getLanguageStoreMap();
    }

    /** @return array<string, array{array<string, bool>, array<int, bool>}> */
    public static function inactiveAdminMapping(): array
    {
        return [
            'inactive language' => [['en_GB' => false], []],
        ];
    }

    /**
     * @param list<array{
     *     mapping_id: int,
     *     store_id: int|null,
     *     language_code: string|null,
     *     sort_order: int,
     *     is_manual: int
     * }> $rows
     * @param array<string, bool> $languages
     * @param array<int, bool> $stores
     */
    private function providerWithRows(
        array $rows,
        array $languages = [],
        array $stores = []
    ): LanguageStoreMappingProvider {
        $stateProvider = $this->createStub(LanguageMappingStateProviderInterface::class);
        $stateProvider->method('getState')->willReturn(new MappingStateDto(
            ['en_GB', 'pl_PL', 'de_DE'],
            $rows,
            [0 => $this->storeView(0, 'admin'), 2 => $this->storeView(2, 'polish'), 4 => $this->storeView(4, 'german')],
            $languages,
            $stores,
            'revision'
        ));
        $cache = $this->createStub(MappingCache::class);
        $cache->method('get')->willReturnCallback(static fn (callable $load): array => $load());
        return new LanguageStoreMappingProvider($stateProvider, $cache);
    }

    /**
     * @return array{mapping_id: int, store_id: int|null, language_code: string|null, sort_order: int, is_manual: int}
     */
    private function row(int $mappingId, ?int $storeId, ?string $languageCode, int $isManual = 1): array
    {
        return [
            'mapping_id' => $mappingId,
            'store_id' => $storeId,
            'language_code' => $languageCode,
            'sort_order' => $mappingId,
            'is_manual' => $isManual,
        ];
    }

    /**
     * @return array{id: int, code: string, name: string, locale: string, website: string, group: string}
     */
    private function storeView(int $storeId, string $code): array
    {
        return [
            'id' => $storeId,
            'code' => $code,
            'name' => $code,
            'locale' => 'en_GB',
            'website' => 'Main',
            'group' => 'Main',
        ];
    }
}
