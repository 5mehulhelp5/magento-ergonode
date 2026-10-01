<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Unit\Model\GraphQl;

use Ergonode\CategoryAttributeConsumer\Model\GraphQl\CategoryAttributeCodeLoader;
use Ergonode\Core\Api\GraphQlQueryClientInterface;
use Ergonode\Core\Model\GraphQl\CursorPaginationGuardFactory;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class CategoryAttributeCodeLoaderTest extends TestCase
{
    public function testLoadsUniqueCodesAcrossAllPages(): void
    {
        $client = $this->createMock(GraphQlQueryClientInterface::class);
        $client->expects(self::exactly(2))->method('query')->willReturnOnConsecutiveCalls(
            ['categoryAttributeList' => [
                'edges' => [['node' => ['code' => 'description']]],
                'pageInfo' => ['hasNextPage' => true, 'endCursor' => 'next'],
            ]],
            ['categoryAttributeList' => [
                'edges' => [
                    ['node' => ['code' => 'description']],
                    ['node' => ['code' => 'banner']],
                ],
                'pageInfo' => ['hasNextPage' => false, 'endCursor' => 'last'],
            ]]
        );

        self::assertSame(
            ['description', 'banner'],
            (new CategoryAttributeCodeLoader(
                $client,
                new CursorPaginationGuardFactory()
            ))->load()
        );
    }

    public function testRejectsIncompletePagination(): void
    {
        $client = $this->createStub(GraphQlQueryClientInterface::class);
        $client->method('query')->willReturn(['categoryAttributeList' => [
            'edges' => [],
            'pageInfo' => ['hasNextPage' => true, 'endCursor' => ''],
        ]]);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('incomplete category attribute list');

        (new CategoryAttributeCodeLoader(
            $client,
            new CursorPaginationGuardFactory()
        ))->load();
    }
}
