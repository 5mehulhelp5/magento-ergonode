<?php

declare(strict_types=1);

namespace Ergonode\Category\Test\Unit\Model\Import;

use Ergonode\Category\Model\Import\CategoryTreeDownloadScope;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CategoryTreeDownloadScopeTest extends TestCase
{
    public function testReusesOneTreePerRunAndSeparatesCredentials(): void
    {
        $scope = new CategoryTreeDownloadScope();
        $calls = 0;
        $download = static function () use (&$calls): array {
            return ['sequence' => ++$calls];
        };
        $scope->execute(function () use ($scope, $download): void {
            self::assertSame(['sequence' => 1], $scope->tree('read:a', $download));
            self::assertSame(['sequence' => 1], $scope->tree('read:a', $download));
            self::assertSame(['sequence' => 2], $scope->tree('write:a', $download));
            self::assertSame(['sequence' => 3], $scope->tree('read:b', $download));
            self::assertSame(['sequence' => 4], $scope->tree('read:a', $download));
        });
        self::assertSame(['sequence' => 5], $scope->tree('read:a', $download));
        self::assertSame(5, $calls);
    }

    public function testExceptionClearsPresenceAndCompleteTreeForNextRun(): void
    {
        $scope = new CategoryTreeDownloadScope();
        try {
            $scope->execute(function () use ($scope): void {
                $scope->presence('a', static fn (): array => ['categoryTree' => null]);
                $scope->tree('read:a', static fn (): array => ['complete' => true]);
                throw new RuntimeException('interrupted');
            });
        } catch (RuntimeException) {
            self::assertSame(['fresh' => true], $scope->execute(
                fn (): array => $scope->presence('a', static fn (): array => ['fresh' => true])
            ));
            self::assertSame(['fresh' => true], $scope->execute(
                fn (): array => $scope->tree('read:a', static fn (): array => ['fresh' => true])
            ));
        }
    }
}
