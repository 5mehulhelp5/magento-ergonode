<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeAdminUi\Test\Unit\Model\Mapping;

use Ergonode\Attribute\Api\ErgonodeAttributeTypeResolverInterface;
use Ergonode\Core\Api\CursorPaginationGuardFactoryInterface;
use Ergonode\Core\Api\CursorPaginationGuardInterface;
use Ergonode\Core\Api\GraphQlQueryClientInterface;
use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\ProductAttributeAdminUi\Model\Mapping\RemoteAttributeMetadataSource;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class RemoteAttributeMetadataSourceTest extends TestCase
{
    public function testFetchesCompleteDefinitionsWithActiveReadCredentialInBothModes(): void
    {
        foreach (['read', 'write'] as $mode) {
            $client = $this->createMock(GraphQlQueryClientInterface::class);
            $client->expects(self::exactly(2))->method('query')
                ->willReturnCallback(static function (string $document, array $variables): array {
                    self::assertStringContainsString('attributeStream', $document);
                    self::assertSame(200, $variables['first']);

                    return $variables['after'] === null
                        ? self::page('color', true, 'cursor-1')
                        : self::page('size', false, null);
                });
            $cache = $this->createMock(CacheInterface::class);
            $cache->method('load')->willReturn(false);
            $cache->expects(self::once())->method('save')->willReturn(true);
            $source = $this->source($client, $cache, $mode, ['cursor-1', null]);

            self::assertSame(['imported' => 2, 'changed' => 2], $source->refresh());
            self::assertSame(['color', 'size'], array_column($source->getAttributes(), 'code'));
            self::assertSame([], $source->getOptions('color'));
        }
    }

    public function testIncompleteSecondPageKeepsPreviousCatalog(): void
    {
        $client = $this->createMock(GraphQlQueryClientInterface::class);
        $client->expects(self::exactly(2))->method('query')
            ->willReturnOnConsecutiveCalls(self::page('color', true, 'cursor-1'), []);
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturn((new Json())->serialize([[
            'code' => 'old', 'label' => 'Old', 'type' => 'text', 'scope' => 'global', 'active' => true,
        ]]));
        $cache->expects(self::never())->method('save');
        $source = $this->source($client, $cache, 'read', ['cursor-1']);

        try {
            $source->refresh();
            self::fail('Expected incomplete response to be rejected.');
        } catch (LocalizedException $exception) {
            self::assertStringContainsString('incomplete attribute list', $exception->getMessage());
        }
        self::assertSame(['old'], array_column($source->getAttributes(), 'code'));
    }

    public function testFetchesCompleteOptionListInBothConnectionModes(): void
    {
        foreach (['read', 'write'] as $mode) {
            $client = $this->createMock(GraphQlQueryClientInterface::class);
            $client->expects(self::exactly(2))->method('query')
                ->willReturnCallback(static function (string $document, array $variables): array {
                    self::assertStringContainsString('attributeOptionList', $document);
                    self::assertSame('manufacturer', $variables['code']);
                    self::assertSame(200, $variables['first']);

                    return $variables['after'] === null
                        ? self::optionPage('first', true, 'cursor-1')
                        : self::optionPage('second', false, null);
                });
            $cache = $this->createMock(CacheInterface::class);
            $cache->method('load')->willReturn(false);
            $cache->expects(self::once())->method('save')->willReturn(true);
            $source = $this->source($client, $cache, $mode, ['cursor-1', null]);

            self::assertSame(['imported' => 2, 'changed' => 2], $source->refreshOptions('manufacturer'));
            self::assertSame(['first', 'second'], array_column($source->getOptions('manufacturer'), 'code'));
        }
    }

    public function testUsesStoreZeroLanguageInsteadOfFirstRemoteTranslation(): void
    {
        $client = $this->createMock(GraphQlQueryClientInterface::class);
        $client->expects(self::exactly(2))->method('query')->willReturnOnConsecutiveCalls(
            ['attributeStream' => [
                'edges' => [['node' => [
                    '__typename' => 'SelectAttribute', 'code' => 'product_type_pim', 'scope' => 'global',
                    'name' => [
                        ['language' => 'pl_PL', 'value' => 'Typ produktu'],
                        ['language' => 'en_GB', 'value' => 'Product type'],
                    ],
                ]]],
                'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
            ]],
            ['attributeOptionList' => [
                'edges' => [
                    ['node' => ['code' => 'shirt', 'name' => [
                        ['language' => 'pl_PL', 'value' => 'Koszula'],
                        ['language' => 'en_GB', 'value' => 'Shirt'],
                    ]]],
                    ['node' => ['code' => 'jacket', 'name' => [
                        ['language' => 'pl_PL', 'value' => 'Kurtka'],
                    ]]],
                ],
                'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
            ]]
        );
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturn(false);
        $cache->expects(self::exactly(2))->method('save')->willReturn(true);
        $source = $this->source($client, $cache, 'read', [null, null], 'en_GB', [0 => 'en_GB', 1 => 'pl_PL']);

        $source->refresh();
        $source->refreshOptions('product_type_pim');

        self::assertSame('Product type', $source->getAttributes()[0]['label']);
        self::assertSame(['Shirt', 'Kurtka'], array_column($source->getOptions('product_type_pim'), 'label'));
        self::assertSame(
            ['pl_PL' => 'Koszula', 'en_GB' => 'Shirt'],
            $source->getOptions('product_type_pim')[0]['names']
        );
    }

    public function testUsesMappedFallbackForMissingDefaultLanguageName(): void
    {
        $client = $this->createStub(GraphQlQueryClientInterface::class);
        $client->method('query')->willReturn(['attributeStream' => [
            'edges' => [['node' => [
                '__typename' => 'SelectAttribute', 'code' => 'product_type_pim', 'scope' => 'global',
                'name' => [
                    ['language' => 'en_GB', 'value' => null],
                    ['language' => 'pl_PL', 'value' => 'Typ produktu'],
                    ['language' => 'cs_CZ', 'value' => 'Typ produktu CZ'],
                ],
            ]]],
            'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
        ]]);
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturn(false);
        $cache->method('save')->willReturn(true);
        $source = $this->source($client, $cache, 'read', [null], 'en_GB', [0 => 'en_GB', 2 => 'pl_PL']);

        $source->refresh();

        self::assertSame('Typ produktu', $source->getAttributes()[0]['label']);
    }

    public function testCacheKeyChangesWithStoreZeroLanguage(): void
    {
        $client = $this->createStub(GraphQlQueryClientInterface::class);
        $client->method('query')->willReturn(self::page('color', false, null));
        $cacheIds = [];
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturn(false);
        $cache->expects(self::exactly(2))->method('save')
            ->willReturnCallback(static function (string $data, string $identifier) use (&$cacheIds): bool {
                $cacheIds[] = $identifier;
                return true;
            });

        $this->source($client, $cache, 'read', [null], 'pl_PL')->refresh();
        $this->source($client, $cache, 'read', [null], 'en_GB')->refresh();

        self::assertCount(2, array_unique($cacheIds));
    }

    public function testCacheKeyChangesWithMappedFallbackLanguage(): void
    {
        $client = $this->createStub(GraphQlQueryClientInterface::class);
        $client->method('query')->willReturn(self::page('color', false, null));
        $cacheIds = [];
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturn(false);
        $cache->expects(self::exactly(2))->method('save')
            ->willReturnCallback(static function (string $data, string $identifier) use (&$cacheIds): bool {
                $cacheIds[] = $identifier;
                return true;
            });

        $this->source($client, $cache, 'read', [null], 'en_GB', [0 => 'en_GB', 1 => 'pl_PL'])->refresh();
        $this->source($client, $cache, 'read', [null], 'en_GB', [0 => 'en_GB', 1 => 'cs_CZ'])->refresh();

        self::assertCount(2, array_unique($cacheIds));
    }

    public function testMissingStoreZeroMappingDisplaysCode(): void
    {
        $client = $this->createStub(GraphQlQueryClientInterface::class);
        $client->method('query')->willReturn(self::optionPage('shirt', false, null));
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturn(false);
        $cache->method('save')->willReturn(true);
        $source = $this->source($client, $cache, 'read', [null], null);

        $source->refreshOptions('product_type_pim');

        self::assertSame('shirt', $source->getOptions('product_type_pim')[0]['label']);
    }

    public function testIncompleteOptionPageKeepsPreviousCatalog(): void
    {
        $client = $this->createMock(GraphQlQueryClientInterface::class);
        $client->expects(self::exactly(2))->method('query')
            ->willReturnOnConsecutiveCalls(self::optionPage('new', true, 'cursor-1'), []);
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturn((new Json())->serialize([[
            'code' => 'old', 'label' => 'Old', 'type' => 'option', 'scope' => 'unknown', 'active' => true,
        ]]));
        $cache->expects(self::never())->method('save');
        $source = $this->source($client, $cache, 'read', ['cursor-1']);

        try {
            $source->refreshOptions('manufacturer');
            self::fail('Expected incomplete option response to be rejected.');
        } catch (LocalizedException $exception) {
            self::assertStringContainsString('incomplete option list', $exception->getMessage());
        }
        self::assertSame(['old'], array_column($source->getOptions('manufacturer'), 'code'));
    }

    public function testUnconfiguredConnectionLeavesNeutralMappingAvailable(): void
    {
        $client = $this->createMock(GraphQlQueryClientInterface::class);
        $client->expects(self::never())->method('query');
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::never())->method('load');
        $config = $this->createStub(ConfigProvider::class);
        $config->method('isEnabled')->willReturn(false);
        $source = new RemoteAttributeMetadataSource(
            $client,
            $this->createStub(ErgonodeAttributeTypeResolverInterface::class),
            $this->createStub(CursorPaginationGuardFactoryInterface::class),
            $config,
            $cache,
            new Json(),
            $this->createStub(LanguageStoreMappingProviderInterface::class)
        );

        self::assertSame([], $source->getAttributes());
    }

    /** @return array<string, mixed> */
    private static function page(string $code, bool $hasMore, ?string $cursor): array
    {
        return ['attributeStream' => [
            'edges' => [['node' => [
                '__typename' => 'SelectAttribute', 'code' => $code, 'scope' => 'global',
                'name' => [['language' => 'pl_PL', 'value' => ucfirst($code)]],
            ]]],
            'pageInfo' => ['hasNextPage' => $hasMore, 'endCursor' => $cursor],
        ]];
    }

    /** @return array<string, mixed> */
    private static function optionPage(string $code, bool $hasMore, ?string $cursor): array
    {
        return ['attributeOptionList' => [
            'edges' => [['node' => [
                'code' => $code,
                'name' => [['language' => 'pl_PL', 'value' => ucfirst($code)]],
            ]]],
            'pageInfo' => ['hasNextPage' => $hasMore, 'endCursor' => $cursor],
        ]];
    }

    /** @param array<int, string|null> $cursors */
    private function source(
        GraphQlQueryClientInterface $client,
        CacheInterface $cache,
        string $mode,
        array $cursors,
        ?string $languageCode = 'pl_PL',
        array $storeMap = []
    ): RemoteAttributeMetadataSource {
        $types = $this->createStub(ErgonodeAttributeTypeResolverInterface::class);
        $types->method('fromDefinitionTypeName')->willReturn('select');
        $types->method('toConsumerType')->willReturn('select');
        $guard = $this->createStub(CursorPaginationGuardInterface::class);
        $guard->method('next')->willReturnOnConsecutiveCalls(...$cursors);
        $guards = $this->createStub(CursorPaginationGuardFactoryInterface::class);
        $guards->method('create')->willReturn($guard);
        $config = $this->createStub(ConfigProvider::class);
        $config->method('getEnvironment')->willReturn('test');
        $config->method('isEnabled')->willReturn(true);
        $config->method('getMode')->willReturn($mode);
        $config->method('getGraphQlUrl')->willReturn('https://example.test/api/graphql/');
        $config->method('getApiKey')->willReturn('key-' . $mode);
        $languages = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languages->method('getAdminLanguageCode')->willReturn($languageCode);
        $languages->method('getLanguageStoreMap')->willReturn($storeMap);

        return new RemoteAttributeMetadataSource($client, $types, $guards, $config, $cache, new Json(), $languages);
    }
}
