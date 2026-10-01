<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Contract;

use Ergonode\Category\Model\Import\CategoryStreamPageReader;
use Ergonode\Category\Model\Provider\CategoryMappingQuery;
use Ergonode\CategoryConsumer\Api\CategoryEntityLoaderInterface;
use Ergonode\CategoryConsumer\Api\CategoryEntitySynchronizerInterface;
use Ergonode\CategoryConsumer\Api\CategoryTreeStateProviderInterface;
use Ergonode\CategoryConsumer\Model\Import\CategoryEntityStreamImporter;
use Ergonode\CategoryConsumer\Model\Provider\CategoryDataMappingProvider;
use Ergonode\Category\Model\Reconciliation\CategoryDeletionCandidateResolver;
use Ergonode\Category\Model\Reconciliation\CategoryIdentityResolver;
use Ergonode\Category\Model\Reconciliation\CategoryNameNormalizer;
use Ergonode\CategoryConsumer\Model\Sync\CategorySourceAvailability;
use Ergonode\CategoryConsumer\Model\Sync\CategorySynchronizationProgress;
use Ergonode\Core\Model\Import\CursorStorage;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CategoryDataExclusionContractTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function cases(): array
    {
        return ['source to target' => ['source'], 'target to source' => ['target'],
            'alternating chain' => ['chain'], 'excluded root' => ['root'], 'active control' => ['control']];
    }

    #[DataProvider('cases')]
    public function testStreamUsesTheSameProtectionAsStructureAndStillAdvancesCursor(string $variant): void
    {
        $state = $this->state($variant);
        $mappings = ['A' => 10, 'B' => 11, 'C' => 12, 'D' => 13, 'visible' => 20];
        $expected = match ($variant) {
            'root' => [],
            'control' => array_keys($mappings),
            default => ['visible'],
        };
        $resolver = new CategoryIdentityResolver(new CategoryNameNormalizer(), new CategoryDeletionCandidateResolver());
        $resolution = $resolver->resolve(2, array_map(static fn (array $row): array => [
            'code' => $row['identifier'], 'parent_code' => $row['parent_identifier'],
            'label' => $row['identifier'], 'active' => $row['active'],
        ], $state['source']), array_map(static fn (array $row): array => [
            'id' => (int)$row['identifier'], 'parent_id' => (int)$row['parent_identifier'],
            'label' => $row['identifier'], 'active' => $row['active'],
        ], $state['target']), $mappings);
        self::assertSame([], $resolution['conflicts']);
        foreach ($mappings as $code => $id) {
            self::assertSame(
                in_array($code, $expected, true) ? 'database' : 'excluded',
                $resolution['assignments'][$code]['source']
            );
            self::assertSame($id, $resolution['assignments'][$code]['magento_category_id']);
        }
        $provider = $this->createMock(CategoryTreeStateProviderInterface::class);
        $provider->expects(self::once())->method('getState')->with(7)->willReturn($state);
        $query = $this->createStub(CategoryMappingQuery::class);
        $query->method('getValidMappingsByCodes')->willReturn(array_map(
            static fn (int $id): array => [['category_tree_id' => 7, 'magento_category_id' => $id]],
            $mappings
        ));
        $reader = $this->createStub(CategoryStreamPageReader::class);
        $reader->method('readAll')->willReturn(['codes' => array_keys($mappings), 'cursor' => 'after-exclusions']);
        $cursor = $this->createMock(CursorStorage::class);
        $cursor->method('get')->willReturn(['cursor' => 'before']);
        $cursor->expects(self::once())->method('save')->with('category_stream', 'after-exclusions');
        $entities = [];
        $operations = [];
        foreach ($expected as $code) {
            $entities[$code] = ['code' => $code, 'labels' => ['en_GB' => 'Imported ' . $code]];
            $operations[] = ['category_id' => $mappings[$code], 'entity' => $entities[$code]];
        }
        $loader = $this->createMock(CategoryEntityLoaderInterface::class);
        $loader->expects($expected === [] ? self::never() : self::once())
            ->method('loadMany')->with($expected)->willReturn($entities);
        $synchronizer = $this->createMock(CategoryEntitySynchronizerInterface::class);
        $synchronizer->expects($expected === [] ? self::never() : self::once())
            ->method('synchronize')->with($operations)
            ->willReturn(['snapshots' => 0, 'attributes' => count($expected)]);
        $language = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $language->method('getLanguageStoreMap')->willReturn([0 => 'en_GB']);
        $result = (new CategoryEntityStreamImporter(
            $reader,
            $cursor,
            new CategoryDataMappingProvider($query, $provider),
            $loader,
            $synchronizer,
            $language,
            $this->createStub(CategorySynchronizationProgress::class),
            $this->createStub(CategorySourceAvailability::class)
        ))->execute();
        self::assertSame(count($expected), $result['fetched']);
        self::assertSame('after-exclusions', $result['cursor']);
    }

    /** @return array<string, mixed> */
    private function state(string $variant): array
    {
        $source = [];
        foreach (['A' => 10, 'B' => 11, 'C' => 12, 'D' => 13, 'visible' => 20] as $code => $id) {
            $source[] = ['identifier' => $code, 'magento_category_id' => $id,
                'active' => $code !== 'A' || !in_array($variant, ['source', 'chain'], true),
                'parent_identifier' => match ($code) {
                    'B' => $variant === 'target' ? 'A' : null,
                    'C' => 'B',
                    'D' => $variant === 'chain' ? null : 'C',
                    default => null,
                }];
        }
        $target = [];
        foreach ([2 => null, 10 => '2', 11 => $variant === 'target' ? '2' : '10',
            12 => '2', 13 => '12', 20 => '2'] as $id => $parent) {
            $target[] = ['identifier' => (string)$id, 'parent_identifier' => $parent,
                'active' => !($variant === 'target' && $id === 10) && !($variant === 'root' && $id === 2)];
        }
        return ['tree' => ['is_active' => true, 'root_category_id' => 2], 'source' => $source, 'target' => $target];
    }
}
