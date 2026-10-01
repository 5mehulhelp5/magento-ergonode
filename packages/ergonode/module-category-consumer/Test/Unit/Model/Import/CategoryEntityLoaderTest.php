<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Model\Import;

use Ergonode\Category\Model\GraphQl\CategoryQueries;

use Ergonode\CategoryConsumer\Model\Import\CategoryEntityLoader;
use Ergonode\Core\Model\GraphQl\Client;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class CategoryEntityLoaderTest extends TestCase
{
    public function testBaseLoaderRequestsNamesWithoutCategoryAttributes(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::once())->method('query')->willReturnCallback(
            static function (string $query, array $variables): array {
                self::assertStringContainsString('name(languages: $languages)', $query);
                self::assertStringNotContainsString('attributeList', $query);
                self::assertSame(['languages' => ['en_US', 'pl_PL'], 'code_0' => 'chairs'], $variables);

                return ['category_0' => ['code' => 'chairs', 'name' => [
                    ['language' => 'pl_PL', 'value' => 'Krzesła'],
                    ['language' => 'en_US', 'value' => 'Chairs'],
                ]]];
            }
        );
        $languages = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languages->method('getLanguageCodes')->willReturn(['en_US', 'pl_PL']);

        $entity = (new CategoryEntityLoader(
            $client,
            $languages,
            new Json(),
            new CategoryQueries()
        ))->load('chairs');

        self::assertNotNull($entity);
        self::assertSame(['en_US' => 'Chairs', 'pl_PL' => 'Krzesła'], $entity['labels']);
        self::assertSame([], $entity['attributes']);
    }

    public function testLoadsOneHundredAndOneUniqueCodesInThreeReadRequests(): void
    {
        $codes = array_map(static fn (int $id): string => 'code-' . $id, range(1, 101));
        $client = $this->createMock(Client::class);
        $client->expects(self::never())->method('queryWriteScope');
        $client->expects(self::exactly(3))->method('query')->willReturnCallback(
            static function (string $query, array $variables): array {
                $result = [];
                foreach ($variables as $name => $code) {
                    if (str_starts_with($name, 'code_')) {
                        $index = substr($name, 5);
                        self::assertStringContainsString('category_' . $index . ': category', $query);
                        $result['category_' . $index] = ['code' => $code, 'name' => []];
                    }
                }
                self::assertLessThanOrEqual(50, count($result));
                return $result;
            }
        );
        $languages = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languages->method('getLanguageCodes')->willReturn(['en_US']);
        $loader = new CategoryEntityLoader(
            $client,
            $languages,
            new Json(),
            new CategoryQueries()
        );
        $entities = $loader->loadMany([...$codes, $codes[0]]);
        self::assertSame($codes, array_keys($entities));
    }
}
