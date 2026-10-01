<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisher\Test\Unit\Model\GraphQl;

use Ergonode\CategoryAttributePublisher\Model\GraphQl\WriteScopeCategoryAttributeCodeLoader;
use Ergonode\Core\Api\GraphQlWriteScopeQueryClientInterface;
use Ergonode\Core\Model\GraphQl\CursorPaginationGuardFactory;
use PHPUnit\Framework\TestCase;

class WriteScopeCategoryAttributeCodeLoaderTest extends TestCase
{
    public function testLoadsWriteScopeCodes(): void
    {
        $client = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $client->expects(self::once())->method('queryWriteScope')->willReturn([
            'categoryAttributeList' => [
                'edges' => [['node' => ['code' => 'description']]],
                'pageInfo' => ['hasNextPage' => false],
            ],
        ]);

        self::assertSame(
            ['description'],
            (new WriteScopeCategoryAttributeCodeLoader(
                $client,
                new CursorPaginationGuardFactory()
            ))->loadWriteScope()
        );
    }
}
