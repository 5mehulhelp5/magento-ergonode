<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Test\Unit\Model\Import;

use Ergonode\Core\Model\GraphQl\Client;
use Ergonode\TemplateConsumer\Model\GraphQl\TemplateQueries;
use Ergonode\TemplateConsumer\Model\Import\TemplateListPageReader;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TemplateListPageReaderTest extends TestCase
{
    #[DataProvider('invalidPages')]
    public function testRejectsMalformedPagesInsteadOfTreatingThemAsDeletions(array $page): void
    {
        $client = $this->createStub(Client::class);
        $client->method('query')->willReturn(['templateList' => $page]);
        $this->expectException(LocalizedException::class);
        (new TemplateListPageReader($client))->read(null);
    }

    public static function invalidPages(): array
    {
        return [
            'missing edges' => [['pageInfo' => ['hasNextPage' => false]]],
            'null node' => [['edges' => [['node' => null]], 'pageInfo' => ['hasNextPage' => false]]],
            'missing code' => [['edges' => [['node' => []]], 'pageInfo' => ['hasNextPage' => false]]],
            'string flag' => [['edges' => [], 'pageInfo' => ['hasNextPage' => 'false']]],
            'empty nonterminal page' => [['edges' => [], 'pageInfo' => ['hasNextPage' => true, 'endCursor' => 'a']]],
        ];
    }

    public function testReadsTemplateListNodes(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::once())
            ->method('query')
            ->with(TemplateQueries::TEMPLATE_LIST, ['first' => 50, 'after' => null])
            ->willReturn([
                'templateList' => [
                    'edges' => [
                        ['node' => ['code' => 'template_a']],
                        ['node' => ['code' => 'template_a']],
                    ],
                    'pageInfo' => ['hasNextPage' => false, 'endCursor' => '2'],
                ],
            ]);

        self::assertSame(
            ['codes' => ['template_a'], 'cursor' => '2', 'has_more' => false],
            (new TemplateListPageReader($client))->read(null)
        );
    }

    public function testRejectsMissingTemplateListBeforeSnapshotReconciliation(): void
    {
        $client = $this->createStub(Client::class);
        $client->method('query')->willReturn([]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('missing templateList');

        (new TemplateListPageReader($client))->read(null);
    }

    public function testRejectsIncompleteConnectionBeforeSnapshotReconciliation(): void
    {
        $client = $this->createStub(Client::class);
        $client->method('query')->willReturn([
            'templateList' => [
                'edges' => [],
            ],
        ]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('templateList response is incomplete');

        (new TemplateListPageReader($client))->read(null);
    }

    public function testRejectsStalledTemplateListCursor(): void
    {
        $client = $this->createStub(Client::class);
        $client->method('query')->willReturn([
            'templateList' => [
                'edges' => [],
                'pageInfo' => ['hasNextPage' => true, 'endCursor' => 'same'],
            ],
        ]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('invalid templateList pagination');

        (new TemplateListPageReader($client))->read('same');
    }
}
