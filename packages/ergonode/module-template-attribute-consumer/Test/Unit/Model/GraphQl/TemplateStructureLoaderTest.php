<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Test\Unit\Model\GraphQl;

use Ergonode\Core\Model\GraphQl\Client;
use Ergonode\Core\Model\GraphQl\PageQueryRetrier;
use Ergonode\TemplateAttributeConsumer\Model\GraphQl\TemplateStructureLoader;
use Ergonode\TemplateAttributeConsumer\Model\GraphQl\TemplateStructureQueries;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TemplateStructureLoaderTest extends TestCase
{
    private Client&MockObject $client;
    private LoggerInterface&MockObject $logger;
    private TemplateStructureLoader $loader;

    protected function setUp(): void
    {
        $this->client = $this->createMock(Client::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->loader = new TemplateStructureLoader(
            $this->client,
            new PageQueryRetrier(),
            $this->logger
        );
    }

    #[DataProvider('incompleteConnections')]
    public function testRejectsMissingOrMalformedAttributesBeforeCleanup(array $connection): void
    {
        $this->logger->expects(self::never())->method('warning');
        $this->client->expects(self::once())->method('query')->willReturn(
            $this->templateResponse($connection, $this->connection([]))
        );
        $this->expectException(LocalizedException::class);
        $this->loader->load('template_a');
    }

    public static function incompleteConnections(): array
    {
        return [
            'missing edges' => [['pageInfo' => ['hasNextPage' => false]]],
            'null edges' => [['edges' => null, 'pageInfo' => ['hasNextPage' => false]]],
            'null attribute' => [['edges' => [['node' => null]], 'pageInfo' => ['hasNextPage' => false]]],
            'missing code' => [['edges' => [['node' => []]], 'pageInfo' => ['hasNextPage' => false]]],
        ];
    }

    public function testLoadsAllConnectionsInOrderAndKeepsFirstDuplicate(): void
    {
        $calls = [];
        $responses = [
            $this->templateResponse(
                $this->connection(['root_a'], true, 'root-1'),
                $this->connection(['section_a'], true, 'section-1', true)
            ),
            $this->templateResponse(
                $this->connection(['root_a', 'root_b']),
                $this->connection(['section_a', 'section_b'], false, null, true)
            ),
            $this->sectionResponse('section_a', $this->connection(['attribute_a'], true, 'attribute-1')),
            $this->sectionResponse('section_a', $this->connection(['attribute_a', 'attribute_b'])),
            $this->sectionResponse('section_b', $this->connection(['attribute_c'])),
        ];
        $this->client->expects(self::exactly(5))
            ->method('query')
            ->willReturnCallback(static function (
                string $document,
                array $variables
            ) use (
                &$calls,
                &$responses
            ): array {
                $calls[] = [$document, $variables];

                return array_shift($responses);
            });
        $this->logger->expects(self::exactly(3))->method('warning');

        $result = $this->loader->load('template_a');

        self::assertSame(
            [['language' => 'en_GB', 'value' => 'Template A']],
            $result['name']
        );
        self::assertSame(['root_a', 'root_b'], $this->codes($result['attributeList']['edges']));
        self::assertSame(['section_a', 'section_b'], $this->codes($result['sectionList']['edges']));
        self::assertSame(
            ['attribute_a', 'attribute_b'],
            $this->codes($result['sectionList']['edges'][0]['node']['attributeList']['edges'])
        );
        self::assertSame(
            ['attribute_c'],
            $this->codes($result['sectionList']['edges'][1]['node']['attributeList']['edges'])
        );
        self::assertSame(
            [
                'code' => 'template_a',
                'attributeFirst' => 50,
                'attributeAfter' => null,
                'sectionFirst' => 50,
                'sectionAfter' => null,
            ],
            $calls[0][1]
        );
        self::assertSame(
            [
                'code' => 'template_a',
                'attributeFirst' => 50,
                'attributeAfter' => 'root-1',
                'sectionFirst' => 50,
                'sectionAfter' => 'section-1',
            ],
            $calls[1][1]
        );
        self::assertSame(TemplateStructureQueries::SECTION_ATTRIBUTES, $calls[2][0]);
        self::assertSame(['code' => 'section_a', 'first' => 50, 'after' => null], $calls[2][1]);
        self::assertSame(['code' => 'section_a', 'first' => 50, 'after' => 'attribute-1'], $calls[3][1]);
    }

    public function testStopsWhenEndCursorIsMissing(): void
    {
        $this->client->expects(self::once())
            ->method('query')
            ->willReturn($this->templateResponse(
                $this->connection(['root_a'], true, null),
                $this->connection([])
            ));
        $this->logger->expects(self::never())->method('warning');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('template attributes pagination did not advance');

        $this->loader->load('template_a');
    }

    public function testStopsWhenPageInfoIsMissing(): void
    {
        $this->assertInvalidPageInfoThrows([
            'edges' => [['node' => ['code' => 'root_a']]],
        ]);
    }

    public function testStopsWhenPageInfoIsNull(): void
    {
        $this->assertInvalidPageInfoThrows([
            'pageInfo' => null,
            'edges' => [['node' => ['code' => 'root_a']]],
        ]);
    }

    public function testAdvancesTemplateConnectionsIndependently(): void
    {
        $calls = [];
        $responses = [
            $this->templateResponse(
                $this->connection(['root_a']),
                $this->connection(['section_a'], true, 'section-1', true)
            ),
            $this->templateResponse(
                $this->connection([]),
                $this->connection(['section_b'], false, null, true)
            ),
            $this->sectionResponse('section_a', $this->connection([])),
            $this->sectionResponse('section_b', $this->connection([])),
        ];
        $this->client->expects(self::exactly(4))
            ->method('query')
            ->willReturnCallback(static function (
                string $document,
                array $variables
            ) use (
                &$calls,
                &$responses
            ): array {
                $calls[] = [$document, $variables];

                return array_shift($responses);
            });
        $this->logger->expects(self::never())->method('warning');

        $result = $this->loader->load('template_a');

        self::assertSame(['root_a'], $this->codes($result['attributeList']['edges']));
        self::assertSame(['section_a', 'section_b'], $this->codes($result['sectionList']['edges']));
        self::assertSame(0, $calls[1][1]['attributeFirst']);
        self::assertNull($calls[1][1]['attributeAfter']);
        self::assertSame(50, $calls[1][1]['sectionFirst']);
        self::assertSame('section-1', $calls[1][1]['sectionAfter']);
    }

    public function testStopsWhenCursorDoesNotChange(): void
    {
        $this->client->expects(self::exactly(2))->method('query')->willReturnOnConsecutiveCalls(
            $this->templateResponse(
                $this->connection(['root_a'], true, 'root-1'),
                $this->connection([])
            ),
            $this->templateResponse(
                $this->connection(['root_b'], true, 'root-1'),
                $this->connection([])
            )
        );
        $this->logger->expects(self::never())->method('warning');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('template attributes pagination did not advance');

        $this->loader->load('template_a');
    }

    public function testStopsWhenNextPageAddsNoUniqueCode(): void
    {
        $this->client->expects(self::exactly(2))->method('query')->willReturnOnConsecutiveCalls(
            $this->templateResponse(
                $this->connection(['root_a'], true, 'root-1'),
                $this->connection([])
            ),
            $this->templateResponse(
                $this->connection(['root_a']),
                $this->connection([])
            )
        );
        $this->logger->expects(self::once())->method('warning');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('template attributes pagination did not advance');

        $this->loader->load('template_a');
    }

    public function testUsesExistingRetrierToReducePageSize(): void
    {
        $attempts = [];
        $this->client->expects(self::exactly(2))
            ->method('query')
            ->willReturnCallback(function (string $document, array $variables) use (&$attempts): array {
                self::assertSame(TemplateStructureQueries::TEMPLATE_DETAILS, $document);
                $attempts[] = $variables['attributeFirst'];
                if ($variables['attributeFirst'] === 50) {
                    throw new LocalizedException(new Phrase('Query is too complex.'));
                }

                return $this->templateResponse($this->connection([]), $this->connection([]));
            });
        $this->logger->expects(self::never())->method('warning');

        $result = $this->loader->load('template_a');

        self::assertSame([50, 25], $attempts);
        self::assertSame([], $result['attributeList']['edges']);
        self::assertSame([], $result['sectionList']['edges']);
    }

    /**
     * @param array<string, mixed> $attributeList
     * @param array<string, mixed> $sectionList
     * @return array<string, mixed>
     */
    private function templateResponse(array $attributeList, array $sectionList): array
    {
        return [
            'template' => [
                'code' => 'template_a',
                'name' => [['language' => 'en_GB', 'value' => 'Template A']],
                'attributeList' => $attributeList,
                'sectionList' => $sectionList,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $attributeList
     * @return array<string, mixed>
     */
    private function sectionResponse(string $code, array $attributeList): array
    {
        return ['section' => ['code' => $code, 'attributeList' => $attributeList]];
    }

    /**
     * @param string[] $codes
     * @return array<string, mixed>
     */
    private function connection(
        array $codes,
        bool $hasNextPage = false,
        ?string $endCursor = null,
        bool $withNames = false
    ): array {
        return [
            'pageInfo' => ['hasNextPage' => $hasNextPage, 'endCursor' => $endCursor],
            'edges' => array_map(
                static fn (string $code): array => [
                    'node' => ['code' => $code] + ($withNames ? [
                        'name' => [['language' => 'en_GB', 'value' => strtoupper($code)]],
                    ] : []),
                ],
                $codes
            ),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $edges
     * @return string[]
     */
    private function codes(array $edges): array
    {
        return array_map(
            static fn (array $edge): string => (string)$edge['node']['code'],
            $edges
        );
    }

    /**
     * @param array<string, mixed> $attributeList
     */
    private function assertInvalidPageInfoThrows(array $attributeList): void
    {
        $this->client->expects(self::once())
            ->method('query')
            ->willReturn($this->templateResponse($attributeList, $this->connection([])));
        $this->logger->expects(self::never())->method('warning');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('template attributes pagination metadata is missing or invalid');

        $this->loader->load('template_a');
    }
}
