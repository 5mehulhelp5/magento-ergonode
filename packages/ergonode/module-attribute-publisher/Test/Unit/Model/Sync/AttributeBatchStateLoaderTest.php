<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Test\Unit\Model\Sync;

use Ergonode\Attribute\Model\AttributeDataNormalizer;
use Ergonode\Attribute\Model\ErgonodeAttributeTypeResolver;
use Ergonode\AttributePublisher\Model\Sync\AttributeStateLoader;
use Ergonode\Core\Api\Exception\GraphQlRequestException;
use Ergonode\Core\Api\GraphQlWriteScopeQueryClientInterface;
use Ergonode\Core\Model\GraphQl\CursorPaginationGuardFactory;
use Magento\Framework\Exception\LocalizedException;
use GraphQL\Language\Parser;
use GraphQL\Language\AST\OperationDefinitionNode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AttributeBatchStateLoaderTest extends TestCase
{
    public function testEmptyCodesNeverRead(): void
    {
        $client = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $client->expects(self::never())->method('queryWriteScope');
        self::assertSame([], $this->loader($client)->loadBatch([]));
    }

    public function testSplitsDefinitionsAtFiftyAndPreservesMissingAndNumericCodes(): void
    {
        $codes = array_merge(['0', '123', 'missing'], array_map(strval(...), range(200, 247)));
        $calls = [];
        $client = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $client->expects(self::exactly(2))->method('queryWriteScope')->willReturnCallback(
            static function (string $document, array $variables) use (&$calls): array {
                self::assertSame(['pl_PL', 'en_US'], $variables['languages']);
                $codes = array_values(array_filter(
                    $variables,
                    static fn (string $key): bool => str_starts_with($key, 'code_'),
                    ARRAY_FILTER_USE_KEY
                ));
                self::assertLessThanOrEqual(50, count($codes));
                $definition = Parser::parse($document)->definitions[0];
                self::assertInstanceOf(OperationDefinitionNode::class, $definition);
                self::assertCount(count($codes), $definition->selectionSet->selections);
                $calls[] = $codes;
                $result = [];
                foreach ($codes as $index => $code) {
                    self::assertStringContainsString('$code_' . $index . ': AttributeCode!', $document);
                    $result['attribute_' . $index] = $code === 'missing' ? null : self::definition($code);
                }
                return $result;
            }
        );
        $states = $this->loader($client)->loadBatch([...$codes, '0'], ['pl_PL', 'en_US']);
        self::assertSame([50, 1], array_map(count(...), $calls));
        self::assertSame($codes, array_map(strval(...), array_keys($states)));
        self::assertNull($states['missing']);
        self::assertSame('0', $states['0']->getCode());
        self::assertSame(['pl_PL' => 'Nazwa'], $states['123']->getNames());
    }

    public function testOnlyUnfinishedOptionsContinueWithIndependentCursorsAndFreshCalls(): void
    {
        $calls = [];
        $client = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $client->expects(self::exactly(6))->method('queryWriteScope')->willReturnCallback(
            static function (string $document, array $variables) use (&$calls): array {
                self::assertSame(['pl_PL'], $variables['languages']);
                self::assertNotEmpty(Parser::parse($document)->definitions);
                $calls[] = $variables;
                if (!str_contains($document, 'attributeOptionList(')) {
                    return [
                        'attribute_0' => self::definition('colors', 'SelectAttribute'),
                        'attribute_1' => self::definition('sizes', 'MultiSelectAttribute'),
                    ];
                }
                self::assertSame(100, $variables['first']);
                if ($variables['after_0'] === null) {
                    return [
                        'options_0' => self::page('shared', 'next'),
                        'options_1' => self::page('shared'),
                    ];
                }
                self::assertSame('colors', $variables['code_0']);
                self::assertSame('next', $variables['after_0']);
                self::assertArrayNotHasKey('code_1', $variables);
                return ['options_0' => self::page('last')];
            }
        );
        $loader = $this->loader($client);
        $batch = $loader->loadBatch(['colors', 'sizes'], ['pl_PL']);
        $repeated = $loader->loadBatch(['colors', 'sizes'], ['pl_PL']);
        self::assertEquals($batch, $repeated);
        self::assertSame(['shared', 'last'], array_map(
            static fn ($option): string => $option->getCode(),
            $batch['colors']->getOptions()
        ));
        self::assertSame(['pl_PL' => 'Etykieta'], $batch['sizes']->getOptions()[0]->getNames());
        self::assertCount(1, $batch['sizes']->getOptions());
        self::assertCount(6, $calls);
    }

    public function testSingleAndBatchReturnTheSameCompleteState(): void
    {
        $client = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $client->expects(self::exactly(4))->method('queryWriteScope')->willReturnCallback(
            static function (string $document, array $variables): array {
                self::assertNull($variables['languages']);
                return str_contains($document, 'attributeOptionList(')
                    ? ['options_0' => self::page('red')]
                    : ['attribute_0' => self::definition('colors', 'SelectAttribute')];
            }
        );
        $loader = $this->loader($client);
        self::assertEquals($loader->load('colors'), $loader->loadBatch(['colors'])['colors']);
    }

    #[DataProvider('incompleteResponses')]
    public function testIncompleteDefinitionIsNeverReturnedAsMissing(array $data): void
    {
        $client = $this->createStub(GraphQlWriteScopeQueryClientInterface::class);
        $client->method('queryWriteScope')->willReturn($data);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('incomplete attribute');
        $this->loader($client)->loadBatch(['first', 'second']);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function incompleteResponses(): array
    {
        return ['absent alias' => [['attribute_0' => null]], 'invalid node' => [
            ['attribute_0' => null, 'attribute_1' => 'invalid'],
        ]];
    }

    public function testDuplicateOptionAcrossPagesIsRejectedPerAttribute(): void
    {
        $client = $this->createStub(GraphQlWriteScopeQueryClientInterface::class);
        $client->method('queryWriteScope')->willReturnOnConsecutiveCalls(
            ['attribute_0' => self::definition('color', 'SelectAttribute')],
            ['options_0' => self::page('same', 'next')],
            ['options_0' => self::page('same')]
        );
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('duplicate code');
        $this->loader($client)->loadBatch(['color']);
    }

    public function testRateLimitOnOptionsPropagatesWithoutIndividualFallback(): void
    {
        $exception = new GraphQlRequestException('Wait', GraphQlRequestException::FAILURE_RATE_LIMIT, 429, 30);
        $client = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $client->expects(self::exactly(2))->method('queryWriteScope')->willReturnCallback(
            static function (string $document) use ($exception): array {
                if (str_contains($document, 'attributeOptionList(')) {
                    throw $exception;
                }
                return [
                    'attribute_0' => self::definition('first', 'SelectAttribute'),
                    'attribute_1' => self::definition('second', 'SelectAttribute'),
                ];
            }
        );
        $this->expectExceptionObject($exception);
        $this->loader($client)->loadBatch(['first', 'second']);
    }

    private function loader(GraphQlWriteScopeQueryClientInterface $client): AttributeStateLoader
    {
        return new AttributeStateLoader(
            $client,
            new ErgonodeAttributeTypeResolver(),
            new AttributeDataNormalizer(),
            new CursorPaginationGuardFactory()
        );
    }

    /** @return array<string, mixed> */
    private static function definition(string $code, string $type = 'TextAttribute'): array
    {
        return [
            '__typename' => $type, 'code' => $code, 'scope' => 'LOCAL',
            'name' => [['language' => 'pl_PL', 'value' => 'Nazwa']],
            'metadata' => [['key' => 'source', 'value' => 'magento']],
        ];
    }

    /** @return array<string, mixed> */
    private static function page(string $code, ?string $cursor = null): array
    {
        return [
            'edges' => [['node' => ['code' => $code, 'name' => [
                ['language' => 'pl_PL', 'value' => 'Etykieta'],
            ]]]],
            'pageInfo' => ['hasNextPage' => $cursor !== null, 'endCursor' => $cursor],
        ];
    }
}
