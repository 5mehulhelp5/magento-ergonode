<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisherAdminUi\Test\Unit\Model;

use Ergonode\Attribute\Model\AttributeDataNormalizer;
use Ergonode\Attribute\Model\ErgonodeAttributeTypeResolver;
use Ergonode\AttributePublisher\Model\Sync\AttributeStateLoader;
use Ergonode\CategoryAttributePublisher\Api\WriteScopeCategoryAttributeCodeLoaderInterface;
use Ergonode\CategoryAttributePublisherAdminUi\Model\Mapping\SourceMetadata;
use Ergonode\Core\Api\GraphQlWriteScopeQueryClientInterface;
use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\Core\Model\GraphQl\CursorPaginationGuardFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SourceMetadataReadCountTest extends TestCase
{
    #[DataProvider('attributeTypes')]
    public function testFiftyDefinitionsShareReadsAndRefreshOnlyAfterReset(string $type, int $expectedReads): void
    {
        $reads = 0;
        $codes = array_map(static fn (int $i): string => 'attribute_' . $i, range(1, 50));
        $config = $this->createStub(ConfigProvider::class);
        $config->method('allowsWrites')->willReturn(true);
        $registry = $this->createMock(WriteScopeCategoryAttributeCodeLoaderInterface::class);
        $registry->expects(self::exactly(2))->method('loadWriteScope')->willReturnCallback(
            static function () use (&$reads, $codes): array {
                $reads++;
                return $codes;
            }
        );
        $client = $this->createStub(GraphQlWriteScopeQueryClientInterface::class);
        $client->method('queryWriteScope')->willReturnCallback(
            static function (string $document, array $variables) use (&$reads, $type): array {
                $reads++;
                $result = [];
                $options = str_contains($document, 'attributeOptionList(');
                foreach ($variables as $key => $code) {
                    if (!str_starts_with($key, 'code_')) {
                        continue;
                    }
                    $alias = ($options ? 'options_' : 'attribute_') . substr($key, 5);
                    $result[$alias] = $options ? [
                        'edges' => [['node' => ['code' => 'red', 'name' => [
                            ['language' => 'pl_PL', 'value' => 'Czerwony'],
                        ]]]],
                        'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                    ] : [
                        '__typename' => $type, 'code' => $code, 'scope' => 'LOCAL',
                        'name' => [['language' => 'pl_PL', 'value' => 'Kolor']],
                    ];
                }
                return $result;
            }
        );
        $source = new SourceMetadata($registry, new AttributeStateLoader(
            $client,
            new ErgonodeAttributeTypeResolver(),
            new AttributeDataNormalizer(),
            new CursorPaginationGuardFactory()
        ), $config);
        self::assertCount(50, $source->getAttributes());
        self::assertSame('local', $source->getAttributes()[0]['scope']);
        if ($type === 'SelectAttribute') {
            self::assertSame('Czerwony', $source->getOptions($codes[0])[0]['label']);
        } else {
            self::assertSame([], $source->getOptions($codes[0]));
        }
        $firstReadCount = $reads;
        $source->reset();
        self::assertCount(50, $source->getAttributes());
        self::assertSame($expectedReads, $firstReadCount);
        self::assertSame(2 * $expectedReads, $reads);
    }

    /** @return array<string, array{string, int}> */
    public static function attributeTypes(): array
    {
        return ['text' => ['TextAttribute', 2], 'select' => ['SelectAttribute', 3]];
    }
}
