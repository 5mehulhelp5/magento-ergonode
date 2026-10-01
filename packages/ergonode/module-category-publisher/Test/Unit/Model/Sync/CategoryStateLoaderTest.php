<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisher\Test\Unit\Model\Sync;

use Ergonode\CategoryPublisher\Model\Sync\CategoryStateLoader;
use Ergonode\Core\Api\GraphQlWriteScopeQueryClientInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class CategoryStateLoaderTest extends TestCase
{
    public function testLoadsCoreCategoryStateWithoutAttributeServices(): void
    {
        $client = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $client->expects(self::once())->method('queryWriteScope')->with(
            self::callback(static fn (string $query): bool => !str_contains($query, 'attributeList')
                && str_contains($query, 'name(languages: $languages_0)')),
            ['code_0' => 'chairs', 'languages_0' => ['pl_PL']]
        )->willReturn(['category_0' => [
            'code' => 'chairs',
            'name' => [['language' => ' pl_PL ', 'value' => 'Krzesła']],
        ]]);

        $state = (new CategoryStateLoader($client))->loadBatch(['chairs' => ['pl_PL']])['chairs'];

        self::assertNotNull($state);
        self::assertSame(['pl_PL' => 'Krzesła'], $state->getNames());
        self::assertSame([], $state->getContributions());
    }

    public function testLoadsCategoryExistenceForWholeBatchWithOneQuery(): void
    {
        $client = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $client->expects(self::once())
            ->method('queryWriteScope')
            ->with(
                self::callback(static function (string $query): bool {
                    return str_contains($query, 'category_0: category(code: $code_0) { code }')
                        && str_contains($query, 'category_1: category(code: $code_1) { code }');
                }),
                ['code_0' => 'chairs', 'code_1' => 'tables']
            )
            ->willReturn([
                'category_0' => ['code' => 'chairs'],
                'category_1' => null,
            ]);

        $states = (new CategoryStateLoader($client))->loadExistenceBatch(['chairs', 'tables']);

        self::assertSame('chairs', $states['chairs']?->getCode());
        self::assertNull($states['tables']);
    }
    public function testBatchSplitsAtFiftyAndPreservesEachLanguageScope(): void
    {
        $scopes = [];
        for ($i = 0; $i < 51; $i++) {
            $scopes['c_' . $i] = $i % 2 === 0 ? ['pl_PL'] : [];
        }
        $sizes = [];
        $client = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $client->expects(self::exactly(2))->method('queryWriteScope')->willReturnCallback(
            static function (string $query, array $variables) use (&$sizes, $scopes): array {
                $data = [];
                for ($i = 0; isset($variables['code_' . $i]); $i++) {
                    $code = $variables['code_' . $i];
                    self::assertSame($scopes[$code] ?: null, $variables['languages_' . $i]);
                    $data['category_' . $i] = ['code' => $code, 'name' => []];
                }
                $sizes[] = count($data);
                return $data;
            }
        );
        $states = (new CategoryStateLoader($client))->loadBatch($scopes);
        self::assertSame([50, 1], $sizes);
        self::assertSame(array_keys($scopes), array_keys($states));
    }

    public function testIncompleteBatchResponseIsNotTreatedAsConfirmedAbsence(): void
    {
        $client = $this->createStub(GraphQlWriteScopeQueryClientInterface::class);
        $client->method('queryWriteScope')->willReturn(['category_0' => null]);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('did not return category "tables"');
        (new CategoryStateLoader($client))->loadBatch(['chairs' => [], 'tables' => []]);
    }

    #[DataProvider('incompleteExistenceResponses')]
    public function testExistenceRequiresAnExplicitNullOrCompleteIdentity(array $response): void
    {
        $client = $this->createStub(GraphQlWriteScopeQueryClientInterface::class);
        $client->method('queryWriteScope')->willReturn($response);
        $this->expectException(LocalizedException::class);
        (new CategoryStateLoader($client))->loadExistenceBatch(['chairs']);
    }

    public static function incompleteExistenceResponses(): array
    {
        return [
            'missing alias' => [[]],
            'wrong type' => [['category_0' => false]],
            'missing code' => [['category_0' => []]],
        ];
    }
}
