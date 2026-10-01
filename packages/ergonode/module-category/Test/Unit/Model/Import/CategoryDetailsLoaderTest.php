<?php

declare(strict_types=1);

namespace Ergonode\Category\Test\Unit\Model\Import;

use Ergonode\Category\Model\GraphQl\CategoryQueries;

use Ergonode\Category\Model\Import\CategoryDetailsLoader;
use Ergonode\Core\Api\GraphQlWriteScopeQueryClientInterface;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CategoryDetailsLoaderTest extends TestCase
{
    #[DataProvider('batchSizes')]
    public function testLoadsNamesInBatchesWithoutPerCategoryRequests(int $count, int $requests): void
    {
        $codes = array_map(static fn (int $i): string => 'code-' . $i, range(1, $count));
        $client = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $client->expects(self::exactly($requests))->method('queryWriteScope')->willReturnCallback(
            static function (string $document, array $variables): array {
                self::assertSame(['pl_PL', 'en_GB'], $variables['languages']);
                unset($variables['languages']);
                self::assertLessThanOrEqual(50, count($variables));
                self::assertSame(count($variables), substr_count($document, ': category(code:'));
                self::assertStringContainsString('name(languages: $languages)', $document);
                $data = [];
                foreach (array_values($variables) as $index => $code) {
                    $data['category_' . $index] = ['code' => $code, 'name' => [
                        ['language' => 'pl_PL', 'value' => 'Krzesła'],
                        ['language' => 'en_GB', 'value' => 'Chairs'],
                    ]];
                }

                return $data;
            }
        );
        $languages = $this->createMock(LanguageStoreMappingProviderInterface::class);
        $languages->expects(self::once())->method('getLanguageCodes')->willReturn(['pl_PL', 'en_GB']);

        $result = (new CategoryDetailsLoader(
            $client,
            $languages,
            new CategoryQueries()
        ))->load($codes);

        self::assertSame($codes, array_keys($result));
        self::assertCount(2, $result[$codes[0]]['name']);
    }

    public static function batchSizes(): array
    {
        return [[50, 1], [51, 2]];
    }

    public function testNoRequestForAnExistingSnapshot(): void
    {
        $client = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $client->expects(self::never())->method('queryWriteScope');
        $languages = $this->createMock(LanguageStoreMappingProviderInterface::class);
        $languages->expects(self::never())->method('getLanguageCodes');

        self::assertSame([], (new CategoryDetailsLoader(
            $client,
            $languages,
            new CategoryQueries()
        ))->load([]));
    }

    #[DataProvider('invalidDetails')]
    public function testRejectsMissingOrMismatchedDetails(array $response): void
    {
        $client = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $client->expects(self::once())->method('queryWriteScope')->willReturn($response);
        $languages = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languages->method('getLanguageCodes')->willReturn(['pl_PL']);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('complete details for category "chairs"');

        (new CategoryDetailsLoader(
            $client,
            $languages,
            new CategoryQueries()
        ))->load(['chairs']);
    }

    public static function invalidDetails(): array
    {
        return [
            [[]],
            [['category_0' => null]],
            [['category_0' => ['code' => 'other', 'name' => []]]],
            [['category_0' => ['code' => 'chairs']]],
        ];
    }
}
