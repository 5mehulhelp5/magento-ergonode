<?php

declare(strict_types=1);

namespace Ergonode\Language\Test\Unit\Model;

use Ergonode\Core\Api\GraphQlQueryClientInterface;
use Ergonode\Language\Model\GraphQl\LanguageQuery;
use Ergonode\Language\Model\LanguageProvider;
use Ergonode\Language\Model\Mapping\MappingLock;
use Ergonode\Language\Model\Mapping\MappingCache;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Ergonode\Language\Model\Storage\LanguageCodeStorage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class LanguageProviderTest extends TestCase
{
    public function testReturnsCodesStoredInMagentoWithoutRemoteRequest(): void
    {
        $client = $this->createMock(GraphQlQueryClientInterface::class);
        $client->expects(self::never())->method('query');
        $storage = $this->createStub(LanguageCodeStorage::class);
        $storage->method('getCodes')->willReturn(['de_DE', 'pl_PL']);

        self::assertSame(
            ['de_DE', 'pl_PL'],
            $this->provider($client, $storage)->getErgonodeLanguageCodes()
        );
    }

    public function testRefreshFetchesNormalizesAndPersistsRemoteCodes(): void
    {
        $client = $this->createMock(GraphQlQueryClientInterface::class);
        $client->expects(self::once())
            ->method('query')
            ->with(LanguageQuery::LANGUAGE_LIST, ['first' => 100, 'after' => null], false)
            ->willReturn(['languageList' => ['edges' => [
                ['node' => 'pl_PL'],
                ['node' => ' en_GB '],
                ['node' => 'pl_PL'],
            ], 'pageInfo' => [
                'hasNextPage' => false,
                'endCursor' => null,
            ]]]);
        $storage = $this->createMock(LanguageCodeStorage::class);
        $storage->expects(self::once())->method('replace')->with(['pl_PL', 'en_GB']);

        self::assertSame(
            ['pl_PL', 'en_GB'],
            $this->provider($client, $storage)->refreshErgonodeLanguageCodes()
        );
    }

    public function testRefreshPaginatesRemoteCodesInPagesOfOneHundred(): void
    {
        $requests = [];
        $client = $this->createMock(GraphQlQueryClientInterface::class);
        $client->expects(self::exactly(2))
            ->method('query')
            ->willReturnCallback(function (string $query, array $variables, bool $useCache) use (&$requests): array {
                self::assertSame(LanguageQuery::LANGUAGE_LIST, $query);
                self::assertFalse($useCache);
                $requests[] = $variables;

                return count($requests) === 1
                    ? ['languageList' => [
                        'edges' => [['node' => 'pl_PL']],
                        'pageInfo' => ['hasNextPage' => true, 'endCursor' => 'cursor-1'],
                    ]]
                    : ['languageList' => [
                        'edges' => [['node' => 'en_GB']],
                        'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                    ]];
            });
        $storage = $this->createMock(LanguageCodeStorage::class);
        $storage->expects(self::once())->method('replace')->with(['pl_PL', 'en_GB']);

        self::assertSame(
            ['pl_PL', 'en_GB'],
            $this->provider($client, $storage)->refreshErgonodeLanguageCodes()
        );
        self::assertSame([
            ['first' => 100, 'after' => null],
            ['first' => 100, 'after' => 'cursor-1'],
        ], $requests);
    }

    public function testInvalidResponsePreservesSnapshot(): void
    {
        foreach ([[], ['languageList' => null], ['languageList' => []],
            ['languageList' => ['edges' => [['node' => 'pl_PL'], ['node' => 123]],
                'pageInfo' => ['hasNextPage' => false, 'endCursor' => null]]],
            ['languageList' => ['edges' => [['node' => '']],
                'pageInfo' => ['hasNextPage' => false, 'endCursor' => null]]],
            ['languageList' => ['edges' => [['node' => str_repeat('x', 65)]],
                'pageInfo' => ['hasNextPage' => false, 'endCursor' => null]]],
            ['languageList' => ['edges' => [],
                'pageInfo' => ['hasNextPage' => true, 'endCursor' => 'cursor-1']]],
        ] as $response) {
            $client = $this->createStub(GraphQlQueryClientInterface::class);
            $client->method('query')->willReturn($response);
            $storage = $this->createMock(LanguageCodeStorage::class);
            $storage->expects(self::never())->method('replace');
            $cache = $this->createMock(MappingCache::class);
            $cache->expects(self::never())->method('invalidate');
            try {
                $this->provider($client, $storage, $cache)->refreshErgonodeLanguageCodes();
                self::fail('Malformed language response was accepted.');
            } catch (LocalizedException $exception) {
                self::assertStringContainsString('snapshot was preserved', $exception->getMessage());
            }
        }
    }

    public function testValidEmptyResponseClearsSnapshotAndInvalidatesCache(): void
    {
        $client = $this->createStub(GraphQlQueryClientInterface::class);
        $client->method('query')->willReturn(['languageList' => [
            'edges' => [],
            'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
        ]]);
        $storage = $this->createMock(LanguageCodeStorage::class);
        $storage->expects(self::once())->method('replace')->with([]);
        $cache = $this->createMock(MappingCache::class);
        $cache->expects(self::once())->method('invalidate');
        self::assertSame([], $this->provider($client, $storage, $cache)->refreshErgonodeLanguageCodes());
    }

    /** @param array<string, mixed>|null $secondPage */
    #[DataProvider('failedSecondPages')]
    public function testFailureOnSecondPageNeverReplacesSnapshot(?array $secondPage): void
    {
        $calls = 0;
        $failure = new RuntimeException('Remote transport failed on page two.');
        $client = $this->createMock(GraphQlQueryClientInterface::class);
        $client->expects(self::exactly(2))->method('query')->willReturnCallback(
            static function (
                string $query,
                array $variables,
                bool $useCache
            ) use (
                &$calls,
                $secondPage,
                $failure
            ): array {
                self::assertSame(LanguageQuery::LANGUAGE_LIST, $query);
                self::assertFalse($useCache);
                self::assertSame(['first' => 100, 'after' => $calls === 0 ? null : 'cursor-1'], $variables);
                if (++$calls === 1) {
                    return ['languageList' => [
                        'edges' => [['node' => 'pl_PL']],
                        'pageInfo' => ['hasNextPage' => true, 'endCursor' => 'cursor-1'],
                    ]];
                }
                if ($secondPage === null) {
                    throw $failure;
                }
                return $secondPage;
            }
        );
        $storage = $this->createMock(LanguageCodeStorage::class);
        $storage->expects(self::never())->method('replace');
        $cache = $this->createMock(MappingCache::class);
        $cache->expects(self::never())->method('invalidate');

        if ($secondPage === null) {
            $this->expectExceptionObject($failure);
        } else {
            $this->expectException(LocalizedException::class);
            $this->expectExceptionMessage('The snapshot was preserved.');
        }
        $this->provider($client, $storage, $cache)->refreshErgonodeLanguageCodes();
    }

    /** @return array<string, array{array<string, mixed>|null}> */
    public static function failedSecondPages(): array
    {
        return [
            'transport failure' => [null],
            'malformed partial page' => [['languageList' => [
                'edges' => [['node' => 'en_GB'], ['node' => null]],
                'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
            ]]],
            'repeated cursor' => [['languageList' => [
                'edges' => [['node' => 'en_GB']],
                'pageInfo' => ['hasNextPage' => true, 'endCursor' => 'cursor-1'],
            ]]],
            'missing continuation cursor' => [['languageList' => [
                'edges' => [['node' => 'en_GB']],
                'pageInfo' => ['hasNextPage' => true, 'endCursor' => null],
            ]]],
        ];
    }

    private function provider(
        GraphQlQueryClientInterface $client,
        LanguageCodeStorage $storage,
        ?MappingCache $cache = null
    ): LanguageProvider {
        $manager = $this->createStub(LockManagerInterface::class);
        $manager->method('lock')->willReturn(true);
        return new LanguageProvider(
            $client,
            $storage,
            new MappingLock($manager),
            $cache ?? $this->createStub(MappingCache::class),
            $manager
        );
    }
}
