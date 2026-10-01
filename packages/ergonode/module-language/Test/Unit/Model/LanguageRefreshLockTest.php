<?php

declare(strict_types=1);

namespace Ergonode\Language\Test\Unit\Model;

use Ergonode\Core\Api\GraphQlQueryClientInterface;
use Ergonode\Language\Model\LanguageProvider;
use Ergonode\Language\Model\Mapping\MappingCache;
use Ergonode\Language\Model\Mapping\MappingLock;
use Ergonode\Language\Model\Storage\LanguageCodeStorage;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class LanguageRefreshLockTest extends TestCase
{
    private const string LOCK = 'ergonode_language_refresh';

    public function testBusyRefreshRejectsBeforeRemoteRequestOrWrite(): void
    {
        $locks = $this->createMock(LockManagerInterface::class);
        $locks->expects(self::once())->method('lock')->with(self::LOCK, 0)->willReturn(false);
        $locks->expects(self::never())->method('unlock');
        $client = $this->createMock(GraphQlQueryClientInterface::class);
        $client->expects(self::never())->method('query');
        $storage = $this->createMock(LanguageCodeStorage::class);
        $storage->expects(self::never())->method('replace');
        $cache = $this->createMock(MappingCache::class);
        $cache->expects(self::never())->method('invalidate');
        $provider = new LanguageProvider($client, $storage, new MappingLock($locks), $cache, $locks);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Languages are already being refreshed. Please try again.');
        $provider->refreshErgonodeLanguageCodes();
    }

    public function testRefreshLockSpansAllPagesWriteAndInvalidation(): void
    {
        $events = [];
        $locks = $this->createMock(LockManagerInterface::class);
        $locks->expects(self::exactly(2))->method('lock')->willReturnCallback(
            static function (string $name, int $timeout) use (&$events): bool {
                $events[] = ['lock', $name, $timeout];
                return true;
            }
        );
        $locks->expects(self::exactly(2))->method('unlock')->willReturnCallback(
            static function (string $name) use (&$events): bool {
                $events[] = ['unlock', $name];
                return true;
            }
        );
        $client = $this->createMock(GraphQlQueryClientInterface::class);
        $client->expects(self::exactly(2))->method('query')->willReturnCallback(
            static function (string $query, array $variables) use (&$events): array {
                $events[] = ['page', $variables['after']];
                return ['languageList' => [
                    'edges' => [['node' => $variables['after'] === null ? 'en_GB' : 'pl_PL']],
                    'pageInfo' => ['hasNextPage' => $variables['after'] === null, 'endCursor' => 'next'],
                ]];
            }
        );
        $storage = $this->createMock(LanguageCodeStorage::class);
        $storage->expects(self::once())->method('replace')->willReturnCallback(
            static function (array $codes) use (&$events): void {
                $events[] = ['replace', $codes];
            }
        );
        $cache = $this->createMock(MappingCache::class);
        $cache->expects(self::once())->method('invalidate')->willReturnCallback(
            static function () use (&$events): void {
                $events[] = ['invalidate'];
            }
        );
        $provider = new LanguageProvider($client, $storage, new MappingLock($locks), $cache, $locks);
        self::assertSame(['en_GB', 'pl_PL'], $provider->refreshErgonodeLanguageCodes());
        self::assertSame([
            ['lock', self::LOCK, 0],
            ['page', null],
            ['page', 'next'],
            ['lock', 'ergonode_language_mapping', 5],
            ['replace', ['en_GB', 'pl_PL']],
            ['invalidate'],
            ['unlock', 'ergonode_language_mapping'],
            ['unlock', self::LOCK],
        ], $events);
    }

    #[DataProvider('failures')]
    public function testFailureReleasesRefreshLock(string $stage): void
    {
        $locks = $this->createStub(LockManagerInterface::class);
        $locks->method('lock')->willReturnCallback(
            static fn (string $name): bool => $stage !== 'mapping' || $name === self::LOCK
        );
        $unlocked = [];
        $locks->method('unlock')->willReturnCallback(static function (string $name) use (&$unlocked): bool {
            $unlocked[] = $name;
            return true;
        });
        $client = $this->createStub(GraphQlQueryClientInterface::class);
        $client->method('query')->willReturnCallback(static function () use ($stage): array {
            if ($stage === 'http') {
                throw new RuntimeException('HTTP failed');
            }
            return $stage === 'validation' ? [] : ['languageList' => [
                'edges' => [['node' => 'en_GB']],
                'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
            ]];
        });
        $storage = $this->createStub(LanguageCodeStorage::class);
        if ($stage === 'storage') {
            $storage->method('replace')->willThrowException(new RuntimeException('Storage failed'));
        }
        $cache = $this->createStub(MappingCache::class);
        if ($stage === 'cache') {
            $cache->method('invalidate')->willThrowException(new RuntimeException('Cache failed'));
        }
        $provider = new LanguageProvider($client, $storage, new MappingLock($locks), $cache, $locks);
        try {
            $provider->refreshErgonodeLanguageCodes();
            self::fail('Expected failure was ignored.');
        } catch (RuntimeException | LocalizedException) {
            self::assertContains(self::LOCK, $unlocked);
            self::assertSame(self::LOCK, $unlocked[array_key_last($unlocked)]);
        }
    }

    /** @return array<string, array{string}> */
    public static function failures(): array
    {
        return [
            'HTTP' => ['http'],
            'response validation' => ['validation'],
            'mapping lock' => ['mapping'],
            'storage' => ['storage'],
            'cache' => ['cache'],
        ];
    }
}
