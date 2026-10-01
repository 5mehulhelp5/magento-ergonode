<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Test\Unit\Model\Provider;

use Ergonode\AttributeConsumer\Model\Import\OptionCacheReconciler;
use Ergonode\AttributeConsumer\Model\Provider\ErgonodeOptionProvider;
use Ergonode\AttributeConsumer\Model\Provider\OptionSnapshotCache;
use Ergonode\Core\Api\MappingVisibilityProviderInterface;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class ErgonodeOptionProviderTest extends TestCase
{
    public function testStreamingReadUsesBoundedPagesAndLeavesNoSnapshotCache(): void
    {
        $select = $this->createMock(Select::class);
        foreach (['from', 'where', 'order'] as $method) {
            $select->method($method)->willReturnSelf();
        }
        $select->expects(self::exactly(3))->method('limit')->with(200)->willReturnSelf();
        $db = $this->createMock(AdapterInterface::class);
        $db->method('select')->willReturn($select);
        $page = 0;
        $db->expects(self::exactly(3))->method('fetchAll')->willReturnCallback(
            static function () use (&$page): array {
                $start = $page * 200 + 1;
                ++$page;
                return array_map(static fn (int $id): array => [
                    'entity_id' => $id, 'sort_order' => 0, 'attribute_code' => 'color',
                    'option_code' => 'o' . $id, 'labels_json' => '{"en_US":"label"}',
                ], range($start, min($start + 199, 401)));
            }
        );
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($db);
        $resource->method('getTableName')->willReturnArgument(0);
        $cache = new OptionSnapshotCache();
        $provider = new ErgonodeOptionProvider(
            $resource,
            new Json(),
            $this->createStub(LanguageStoreMappingProviderInterface::class),
            $this->createStub(MappingVisibilityProviderInterface::class),
            $cache
        );
        $count = 0;
        foreach ($provider->iterateOptionDefinitions(['color']) as $option) {
            ++$count;
            self::assertSame('o' . $count, $option['code']);
        }
        self::assertSame(401, $count);
        self::assertSame([], $cache->definitions);
    }

    public function testCacheRejectsLargeOptionSetsAndOversizedLabels(): void
    {
        $cache = new OptionSnapshotCache();
        self::assertFalse($cache->canRetain(['color' => array_fill(0, 501, ['code' => 'x', 'labels' => []])]));
        self::assertFalse($cache->canRetain(['color' => [[
            'code' => 'x', 'labels' => ['en_US' => str_repeat('x', 1048577)],
        ]]]));
        self::assertTrue($cache->canRetain(['empty' => [], 'color' => [['code' => 'x', 'labels' => []]]]));
    }

    public function testBulkReadIsCachedAndReconciliationInvalidatesIt(): void
    {
        $select = $this->createStub(Select::class);
        foreach (['from', 'where', 'order', 'limit'] as $method) {
            $select->method($method)->willReturnSelf();
        }
        $db = $this->createMock(AdapterInterface::class);
        $db->method('select')->willReturn($select);
        $db->expects(self::exactly(2))->method('fetchAll')->willReturnOnConsecutiveCalls(
            [['entity_id' => 1, 'sort_order' => 0, 'attribute_code' => 'color',
                'option_code' => 'red', 'labels_json' => '{"en_US":"Red"}']],
            []
        );
        $db->expects(self::once())->method('delete')->willReturn(1);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($db);
        $resource->method('getTableName')->willReturnArgument(0);
        $cache = new OptionSnapshotCache();
        $provider = new ErgonodeOptionProvider(
            $resource,
            new Json(),
            $this->createStub(LanguageStoreMappingProviderInterface::class),
            $this->createStub(MappingVisibilityProviderInterface::class),
            $cache
        );
        $definitions = $provider->getOptionDefinitionsByCodes(['color', 'size']);
        self::assertSame([], $definitions['size']);
        self::assertSame($definitions['color'], $provider->getOptionDefinitions('color'));
        self::assertSame($definitions, $provider->getOptionDefinitionsByCodes(['color', 'size']));
        (new OptionCacheReconciler($resource, $cache))->reconcile('color', []);
        self::assertSame([], $provider->getOptionDefinitions('color'));
    }
}
