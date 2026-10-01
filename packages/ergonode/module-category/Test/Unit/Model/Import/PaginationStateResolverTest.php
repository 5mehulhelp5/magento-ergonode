<?php

declare(strict_types=1);

namespace Ergonode\Category\Test\Unit\Model\Import;

use Ergonode\Category\Model\Import\PaginationStateResolver;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PaginationStateResolverTest extends TestCase
{
    public function testResolvesAdvancingAndTerminalPaginationStates(): void
    {
        $resolver = new PaginationStateResolver();

        self::assertSame(
            ['cursor' => 'cursor-2', 'has_more' => true],
            $resolver->resolve(
                ['hasNextPage' => true, 'endCursor' => ' cursor-2 '],
                'cursor-1',
                __('Invalid pagination.')
            )
        );
        self::assertSame(
            ['cursor' => 'terminal', 'has_more' => false],
            $resolver->resolve(
                ['hasNextPage' => false],
                'cursor-2',
                __('Invalid pagination.'),
                'terminal'
            )
        );
    }

    /** @return array<string, array{array<string, mixed>, string|null}> */
    public static function invalidStateProvider(): array
    {
        return [
            'missing cursor' => [['hasNextPage' => true], null],
            'repeated cursor' => [['hasNextPage' => true, 'endCursor' => 'cursor-1'], 'cursor-1'],
        ];
    }

    /** @param array<string, mixed> $pageInfo */
    #[DataProvider('invalidStateProvider')]
    public function testRejectsInvalidAdvancingState(array $pageInfo, ?string $currentCursor): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Confirmed pagination failure.');

        (new PaginationStateResolver())->resolve(
            $pageInfo,
            $currentCursor,
            __('Confirmed pagination failure.')
        );
    }
}
