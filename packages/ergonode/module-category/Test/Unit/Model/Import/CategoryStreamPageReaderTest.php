<?php

declare(strict_types=1);

namespace Ergonode\Category\Test\Unit\Model\Import;

use Ergonode\Category\Model\Import\CategoryStreamPageReader;
use Ergonode\Category\Model\Import\PaginationStateResolver;
use Ergonode\Core\Model\GraphQl\Client;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class CategoryStreamPageReaderTest extends TestCase
{
    public function testReadAllCombinesPagesDeduplicatesCodesAndReturnsLastCursor(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::exactly(2))->method('query')->willReturnOnConsecutiveCalls(
            [
                'categoryStream' => [
                    'pageInfo' => ['hasNextPage' => true, 'endCursor' => 'cursor-1'],
                    'edges' => [
                        ['cursor' => 'edge-1', 'node' => ['code' => ' chairs ']],
                        ['cursor' => 'edge-2', 'node' => ['code' => 'tables']],
                    ],
                ],
            ],
            [
                'categoryStream' => [
                    'pageInfo' => ['hasNextPage' => false, 'endCursor' => 'cursor-2'],
                    'edges' => [
                        ['cursor' => 'edge-3', 'node' => ['code' => 'chairs']],
                    ],
                ],
            ]
        );

        self::assertSame(
            ['codes' => ['chairs', 'tables'], 'cursor' => 'cursor-2'],
            (new CategoryStreamPageReader($client, new PaginationStateResolver()))
                ->readAll('query CategoryStream', 'categoryStream', null)
        );
    }

    public function testReadAllRejectsCursorThatDoesNotAdvance(): void
    {
        $client = $this->createStub(Client::class);
        $client->method('query')->willReturn([
            'categoryStream' => [
                'pageInfo' => ['hasNextPage' => true, 'endCursor' => 'cursor-1'],
                'edges' => [],
            ],
        ]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('invalid stream pagination');

        (new CategoryStreamPageReader($client, new PaginationStateResolver()))->readAll(
            'query CategoryStream',
            'categoryStream',
            'cursor-1'
        );
    }
}
