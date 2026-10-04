<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Unit\Model\Pipeline;

use Ergonode\Product\Api\{MagentoIdentityAttributeInterface, ProductIdentityServiceInterface};
use Ergonode\Product\Model\Config\ProductIdentityModeProvider;
use Ergonode\ProductAttributeConsumer\Api\SkuIdentityMappingResolverInterface;
use Ergonode\ProductConsumer\Model\Data\ProductImportWorkItem;
use Ergonode\ProductConsumer\Model\Magento\{MappedMagentoSkuResolver, ProductTargetResolver};
use Ergonode\ProductConsumer\Model\Pipeline\{BatchContext, BatchEntry};
use Ergonode\ProductConsumer\Model\ResourceModel\BatchProductTargets;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;
use Magento\Catalog\Model\ResourceModel\Product;
use Magento\Eav\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class BatchProductTargetsTest extends TestCase
{
    #[DataProvider('unavailableNewModes')]
    public function testUnavailableNewModeDoesNotBlockPersistedIdentitiesInMixedBatch(string $mode): void
    {
        $entries = []; $rows = [];
        foreach (['shared', 'assigned', 'mapped', 'new'] as $index => $historicalMode) {
            $entry = new BatchEntry(new ProductImportWorkItem($index + 1, $historicalMode, 'sync', null, 'e', 'l', 1));
            $entry->source = new RemoteProduct($historicalMode, 'simple', 'template', false, [], []);
            $entries[] = $entry;
            if ($historicalMode !== 'new') {
                $rows[] = ['entity_id' => (string)($index + 40), 'sku' => 'magento-' . $historicalMode,
                    'type_id' => 'simple', 'attribute_set_id' => '4',
                    'ergonode_sku' => $historicalMode, 'identity_mode' => $historicalMode];
            }
        }
        // Selected imports already carry a Magento product ID.
        $entries[2]->productId = 42;
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(self::anything(), self::callback(
            static fn(array $data): bool => $data['ergonode_sku'] === 'new'
                && $data['stage'] === 'preprocess:identity'
                && $data['exception'] instanceof LocalizedException
        ));
        $context = new BatchContext($entries, $logger);
        $resource = $this->resourceWithRows($rows);
        $config = $this->createStub(ScopeConfigInterface::class);
        $config->method('getValue')->willReturn($mode);
        $attribute = $this->createStub(MagentoIdentityAttributeInterface::class);
        $attribute->method('validate')->willThrowException(new LocalizedException(__('Identity attribute is unavailable.')));
        $skus = new MappedMagentoSkuResolver(
            new ProductIdentityModeProvider($config, [], $attribute),
            $this->createStub(SkuIdentityMappingResolverInterface::class)
        );
        $eav = $this->createMock(Config::class);
        $eav->expects(self::never())->method('getAttribute');

        $targets = (new BatchProductTargets($resource, $skus, $attribute, $eav,
            $this->createStub(Product::class)))->get($context);

        foreach (['shared', 'assigned', 'mapped'] as $index => $historicalMode) {
            self::assertNull($entries[$index]->error);
            self::assertSame($index + 40, $targets[$historicalMode]['product_id']);
            self::assertSame($historicalMode, $targets[$historicalMode]['identity_mode']);
            self::assertTrue($targets[$historicalMode]['bound']);
        }
        self::assertArrayNotHasKey('new', $targets);
        self::assertSame('preprocess:identity', $entries[3]->failedStage);
        self::assertInstanceOf(LocalizedException::class, $entries[3]->error);
        $processed = [];
        foreach ($entries as $entry) {
            $context->run($entry, 'process:attributes', function () use (&$processed, $entry): void {
                $processed[] = $entry->sku();
            });
        }
        self::assertSame(['shared', 'assigned', 'mapped'], $processed);
    }

    public static function unavailableNewModes(): array
    {
        return [
            'unset' => [''],
            'legacy shared' => ['shared'],
            'assigned support absent' => ['assigned'],
            'mapped attribute unavailable' => ['mapped'],
        ];
    }

    public function testDeletionBatchDoesNotRequireNewIdentityConfiguration(): void
    {
        $entry = new BatchEntry(new ProductImportWorkItem(1, 'deleted', 'delete', null, 'e', 'l', 1));
        $skus = $this->createMock(MappedMagentoSkuResolver::class);
        $skus->expects(self::never())->method('getConfiguredMode');
        $skus->expects(self::never())->method('resolve');
        $context = new BatchContext([$entry], new NullLogger());
        $resource = $this->resourceWithRows([
            ['entity_id' => '42', 'sku' => 'magento-deleted', 'type_id' => 'simple', 'attribute_set_id' => '4',
                'ergonode_sku' => 'deleted', 'identity_mode' => 'shared'],
        ]);

        $targets = (new BatchProductTargets($resource, $skus,
            $this->createStub(MagentoIdentityAttributeInterface::class),
            $this->createStub(Config::class), $this->createStub(Product::class)))->get($context);

        self::assertNull($entry->error);
        self::assertSame(42, $targets['deleted']['product_id']);
        self::assertSame('shared', $targets['deleted']['identity_mode']);
    }

    public function testEmptyBatchDoesNotReadConfigurationOrProducts(): void
    {
        $skus = $this->createMock(MappedMagentoSkuResolver::class);
        $skus->expects(self::never())->method('getConfiguredMode');
        $resource = $this->createMock(ResourceConnection::class);
        $resource->expects(self::never())->method('getConnection');

        self::assertSame([], (new BatchProductTargets($resource, $skus,
            $this->createStub(MagentoIdentityAttributeInterface::class),
            $this->createStub(Config::class), $this->createStub(Product::class)))
            ->get(new BatchContext([], new NullLogger())));
    }

    private function resourceWithRows(array $rows): ResourceConnection
    {
        $db = $this->createMock(AdapterInterface::class);
        $query = $this->createStub(Select::class);
        foreach (['from', 'joinLeft', 'where'] as $method) { $query->method($method)->willReturnSelf(); }
        $db->method('select')->willReturn($query);
        $db->method('quoteInto')->willReturnArgument(0);
        $db->expects(self::once())->method('fetchAll')->with($query)->willReturn($rows);
        $db->expects(self::never())->method('fetchRow');
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($db);
        $resource->method('getTableName')->willReturnArgument(0);
        return $resource;
    }

    public function testOneExistenceQueryPreparesExistingAndMissingProductsAndResolverDoesNotRepeatIt(): void
    {
        $entries = [];
        foreach (['exists', 'new'] as $sku) {
            $entry = new BatchEntry(new ProductImportWorkItem(1, $sku, 'sync', null, 'e', 'l', 1));
            $entry->source = new RemoteProduct($sku, 'simple', 'template', false, [], []);
            $entries[] = $entry;
        }
        $context = new BatchContext($entries, new NullLogger());
        $db = $this->createMock(AdapterInterface::class);
        $query = $this->createStub(Select::class);
        foreach (['from', 'joinLeft', 'where'] as $method) { $query->method($method)->willReturnSelf(); }
        $db->method('select')->willReturn($query);
        $db->method('quoteInto')->willReturnArgument(0);
        $db->expects(self::once())->method('fetchAll')->with($query)->willReturn([
            ['entity_id' => '42', 'sku' => 'exists', 'type_id' => 'simple', 'attribute_set_id' => '4',
                'ergonode_sku' => 'exists', 'identity_mode' => 'shared'],
        ]);
        $db->expects(self::never())->method('fetchRow');
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($db);
        $resource->method('getTableName')->willReturnArgument(0);
        $skus = $this->createStub(MappedMagentoSkuResolver::class);
        $skus->method('getConfiguredMode')->willReturn('shared');
        $skus->method('resolve')->willReturnCallback(static fn(RemoteProduct $source): string => $source->sku);
        $identityAttribute = $this->createStub(MagentoIdentityAttributeInterface::class);
        $targets = (new BatchProductTargets($resource, $skus, $identityAttribute,
            $this->createStub(Config::class), $this->createStub(Product::class)))->get($context);
        self::assertSame(42, $entries[0]->productId);
        self::assertNull($entries[1]->productId);
        self::assertNull($targets['new']);
        $identities = $this->createMock(ProductIdentityServiceInterface::class);
        $identities->expects(self::never())->method('getIdentitiesByErgonodeSkus');
        $identities->expects(self::never())->method('bind');
        $resolver = new ProductTargetResolver($resource, $identities, $identityAttribute);
        $resolver->setBatchTargets($targets);
        self::assertSame(42, $resolver->resolve('exists')['product_id']);
        self::assertNull($resolver->resolve('new'));
    }

    public function testExistingMappingHasPriorityWhileAmbiguousUnmappedIdentityFailsOnlyItsProduct(): void
    {
        $entries = [];
        foreach (['bound', 'ambiguous'] as $sku) {
            $entry = new BatchEntry(new ProductImportWorkItem(1, $sku, 'sync', null, 'e', 'l', 1));
            $entry->source = new RemoteProduct($sku, 'simple', 'template', false, [], []);
            $entries[] = $entry;
        }
        $db = $this->createMock(AdapterInterface::class);
        $query = $this->createStub(Select::class);
        foreach (['from', 'joinLeft', 'where', 'columns'] as $method) { $query->method($method)->willReturnSelf(); }
        $db->method('select')->willReturn($query);
        $db->method('quoteInto')->willReturnArgument(0);
        $rows = [];
        foreach ([10 => ['bound', 'bound'], 11 => [null, 'bound'],
            12 => [null, 'ambiguous'], 13 => [null, 'ambiguous']] as $id => [$mapping, $native]) {
            $rows[] = ['entity_id' => (string)$id, 'sku' => 'magento-' . $id, 'type_id' => 'simple',
                'attribute_set_id' => '4', 'ergonode_sku' => $mapping,
                'identity_mode' => $mapping !== null ? 'mapped' : null, 'native_sku' => $native];
        }
        $db->expects(self::once())->method('fetchAll')->willReturn($rows);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($db);
        $resource->method('getTableName')->willReturnArgument(0);
        $skus = $this->createStub(MappedMagentoSkuResolver::class);
        $skus->method('getConfiguredMode')->willReturn('mapped');
        $skus->method('resolve')->willReturn('');
        $identityAttribute = $this->createStub(MagentoIdentityAttributeInterface::class);
        $identityAttribute->method('getCode')->willReturn('ergonode_sku');
        $attribute = $this->createStub(\Magento\Eav\Model\Entity\Attribute::class);
        $attribute->method('getBackendType')->willReturn('static');
        $eav = $this->createStub(Config::class);
        $eav->method('getAttribute')->willReturn($attribute);
        $context = new BatchContext($entries, new NullLogger());

        $targets = (new BatchProductTargets($resource, $skus, $identityAttribute, $eav,
            $this->createStub(Product::class)))->get($context);

        self::assertSame(10, $targets['bound']['product_id']);
        self::assertNull($entries[0]->error);
        self::assertArrayNotHasKey('ambiguous', $targets);
        self::assertSame('preprocess:identity', $entries[1]->failedStage);
        self::assertStringContainsString('matches multiple Magento products', $entries[1]->error->getMessage());
    }
}
