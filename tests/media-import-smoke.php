<?php

declare(strict_types=1);

use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\Media\Model\Download\DownloadGuard;
use Ergonode\Media\Model\Gallery\GalleryScheduler;
use Ergonode\Media\Model\Gallery\GalleryWorkProcessor;
use Ergonode\Media\Model\Gallery\WorkProcessor;
use Ergonode\Media\Model\GraphQl\MultimediaClient;
use Ergonode\Media\Model\Index\IndexedFileResolver;
use Ergonode\Media\Model\Index\LocalFiles;
use Ergonode\Media\Model\Materialization\AssetMaterializer;
use Ergonode\Media\Model\Materialization\DefaultSharedPathStrategy;
use Ergonode\Media\Model\Materialization\MaterializationCache;
use Ergonode\Media\Model\Materialization\ProductPathStrategy;
use Ergonode\Media\Model\Materialization\SourcePreparer;
use Ergonode\Media\Model\Queue\Consumer;
use Ergonode\Media\Model\Queue\QueuePublisher;
use Ergonode\Media\Model\ResourceModel\MediaRepository;
use Ergonode\ProductConsumer\Model\ValueObject\Product\Attribute\LocalizedStringListValues;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProductAttribute;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProductAttributeType;
use Ergonode\ProductMedia\Api\GalleryConfigurationInterface;
use Ergonode\ProductMediaConsumer\Model\Media\ProductGallerySelectionProvider;
use Ergonode\ProductMediaConsumer\Model\Media\ProductMediaSynchronizer;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Lock\LockManagerInterface;

require dirname(__DIR__) . '/app/bootstrap.php';
$om = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER)->getObjectManager();
$om->get(\Magento\Framework\App\State::class)->setAreaCode('adminhtml');
$resource = $om->get(ResourceConnection::class);
$db = $resource->getConnection();
$prefix = 'media-smoke-' . bin2hex(random_bytes(8));
$root = BP . '/var/tmp/' . $prefix;
mkdir($root . '/media/catalog/product', 0777, true);
mkdir($root . '/fixtures', 0777, true);
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aU1sAAAAASUVORK5CYII=');
file_put_contents($root . '/fixtures/first.png', $png . $prefix . '-first');
file_put_contents($root . '/fixtures/replacement.png', $png . $prefix . '-replacement');
$socket = stream_socket_server('tcp://127.0.0.1:0', $error, $message);
if ($socket === false) {
    throw new RuntimeException('Cannot allocate the local fixture server.');
}
$address = stream_socket_get_name($socket, false);
fclose($socket);
$server = proc_open([PHP_BINARY, '-S', $address, __DIR__ . '/fixtures/media-server.php'],
    [0 => ['pipe', 'r'], 1 => ['file', $root . '/server.log', 'a'], 2 => ['file', $root . '/server.log', 'a']],
    $pipes, BP, array_merge(getenv(), ['ERGONODE_MEDIA_TEST_FIXTURES' => $root . '/fixtures']));
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $assertions++;
};
$initialCounts = [];
foreach (['catalog_product_entity', 'catalog_product_entity_media_gallery', 'ergonode_media_asset',
    'ergonode_media_materialization', 'ergonode_media_product_usage', 'ergonode_media_product_work',
    'ergonode_media_local_file'] as $table) {
    $initialCounts[$table] = (int)$db->fetchOne('SELECT COUNT(*) FROM ' . $resource->getTableName($table));
}
$db->beginTransaction();
try {
    $assert((int)$db->fetchOne('SELECT COUNT(*) FROM ' . $resource->getTableName('ergonode_media_product_work')) === 0,
        'Run this smoke test only when the media work queue is empty.');
    for ($attempt = 0; $attempt < 100; $attempt++) {
        $ready = @stream_socket_client('tcp://' . $address, $error, $message, 0.1);
        if ($ready !== false) {
            fclose($ready);
            break;
        }
        usleep(10000);
    }
    $assert($ready !== false, 'The local fixture server did not start.');
    $directories = new DirectoryList($root, [DirectoryList::MEDIA => ['path' => $root . '/media'],
        DirectoryList::VAR_DIR => ['path' => $root . '/var']]);
    $curl = new class extends Curl {
        public int $downloads = 0;
        public function get($uri)
        {
            $this->downloads++;
            parent::get($uri);
        }
    };
    $config = new class($address) extends ConfigProvider {
        public function __construct(private readonly string $address) {}
        public function getGraphQlUrl(): string { return "http://" . $this->address . "/api/graphql/"; }
        public function getApiKey(): string { return ''; }
        public function isEnabled(): bool { return true; }
    };
    $files = new class extends File {
        public int $copies = 0;
        public int $targetChecks = 0;
        public function cp($src, $destination)
        {
            $this->copies++;
            return parent::cp($src, $destination);
        }
        public function fileExists($file, $onlyFile = true)
        {
            if (str_contains($file, '/media/catalog/product/') && str_ends_with($file, '.png')) {
                $this->targetChecks++;
            }
            return parent::fileExists($file, $onlyFile);
        }
    };
    $localFiles = new class($directories, new \Magento\Framework\Filesystem\Driver\File()) extends LocalFiles {
        public int $hashes = 0;
        public function hash(string $path): string
        {
            $this->hashes++;
            return parent::hash($path);
        }
    };
    $publisher = new class extends QueuePublisher {
        public function __construct() {}
        public function dispatch(): void {}
    };
    $galleryConfig = new class implements GalleryConfigurationInterface {
        public function isSynchronizationEnabled(): bool { return true; }
        public function getGalleryAttributeCode(): string { return 'smoke_photos'; }
    };
    $repository = $om->get(MediaRepository::class);
    $cache = new MaterializationCache();
    $locks = $om->get(LockManagerInterface::class);
    $curlFactory = new class($curl) extends \Magento\Framework\HTTP\Client\CurlFactory {
        public function __construct(private readonly \Magento\Framework\HTTP\Client\Curl $client) {}
        public function create(array $data = []) { return $this->client; }
    };
    $preparer = new SourcePreparer($repository, $om->get(MultimediaClient::class), $curlFactory,
        new \Ergonode\Core\Model\Http\DownloadSourcePolicy($config),
        $om->get(DownloadGuard::class), $directories, new File(), $locks);
    $materializer = new AssetMaterializer($repository, $preparer, $directories, $files, $locks,
        new DefaultSharedPathStrategy(), $om->get(ProductPathStrategy::class),
        new IndexedFileResolver($om->get(\Ergonode\Media\Model\Port\LocalFileIndexInterface::class), $localFiles), $cache);
    $gallery = new GalleryWorkProcessor($repository,
        $om->get(\Ergonode\Media\Model\Config\GalleryModeProvider::class),
        $om->get(\Ergonode\Media\Api\GalleryModeLockInterface::class), $materializer,
        $om->get(\Ergonode\ProductMedia\Api\GallerySynchronizerInterface::class),
        $om->get(\Ergonode\ProductMedia\Api\ImageRolesInterface::class));
    $worker = new WorkProcessor($repository, $gallery,
        $om->get(\Ergonode\Media\Api\SharedAssetResolverInterface::class),
        $om->get(\Ergonode\Media\Model\Port\FileAttributeWriterInterface::class), $galleryConfig,
        $om->get(\Ergonode\ProductMedia\Api\ImageRolesInterface::class),
        $om->get(\Ergonode\ProductMedia\Model\Gallery\GalleryWriteLocks::class));
    $selection = new ProductGallerySelectionProvider($galleryConfig,
        $om->get(\Ergonode\ProductMediaConsumer\Model\Media\AdditionalImageSelection::class),
        $om->get(\Ergonode\ProductMedia\Api\GalleryLayoutInterface::class));
    $languages = new class extends \Ergonode\Language\Model\Mapping\LanguageStoreMappingProvider {
        public function __construct() {}
        public function getLanguageStoreMap(): array { return [0 => 'pl_PL']; }
    };
    $fileUsages = new \Ergonode\ProductMediaConsumer\Model\Magento\ProductFileUsageSynchronizer(
        $om->get(\Ergonode\ProductAttributeConsumer\Api\ProductAttributeMappingProviderInterface::class),
        $languages, $om->get(\Ergonode\Media\Api\FileUsageRecorderInterface::class),
        $om->get(\Magento\Eav\Model\Config::class),
        new \Ergonode\ProductMediaConsumer\Model\Magento\AsynchronousFileAttributeMapping(),
        $om->get(\Magento\Store\Model\StoreManagerInterface::class));
    $bridge = new ProductMediaSynchronizer($fileUsages, $selection,
        new GalleryScheduler($galleryConfig, $repository, $publisher));
    $productResource = $om->get(ProductResource::class);
    $ids = [];
    foreach ([1, 2, 3] as $number) {
        $product = $om->create(Product::class);
        $product->setTypeId('simple')->setAttributeSetId(4)->setSku($prefix . '-' . $number)
            ->setName('Isolated media import fixture')->setPrice(10)->setStatus(1)->setVisibility(4);
        $productResource->save($product);
        $ids[] = (int)$product->getId();
    }
    $sourcePath = '/' . $prefix . '/first.png';
    $aliasPath = '/' . $prefix . '/alias.png';
    $item = static fn (string $path, string $url, string $cursor): array => [
        'path' => $path, 'url' => $url, 'cursor' => $cursor, 'name' => 'fixture',
        'extension' => 'png', 'mime' => 'image/png', 'size' => 0,
    ];
    $repository->recordStream([$item($sourcePath, 'http://' . $address . '/first.png', 'first'),
        $item($aliasPath, 'http://' . $address . '/first.png', 'alias')]);
    foreach ($ids as $offset => $id) {
        $path = $offset === 2 ? $aliasPath : $sourcePath;
        $source = new RemoteProduct($prefix . '-' . ($offset + 1), 'simple', 'fixture', false, [], [
            new RemoteProductAttribute('smoke_photos', new RemoteProductAttributeType('gallery'),
                new LocalizedStringListValues(['pl_PL' => [$path]])),
        ]);
        $bridge->synchronize($id, $source->sku, $source);
    }
    $cache->run(function () use ($gallery, $ids, $materializer, $repository, $sourcePath, $assert): void {
        foreach ($ids as $id) {
            $gallery->process($id);
        }
        $asset = $repository->ensureAsset($sourcePath);
        $path = $materializer->shared($asset, 'catalog/product/ergonode/shared');
        for ($product = 0; $product < 100; $product++) {
            $assert($materializer->shared($asset, 'catalog/product/ergonode/shared') === $path,
                'A repeated product resolved a different path.');
        }
    });
    $firstPath = $repository->galleryUsages($ids[0])[0]['attached_path'];
    $assert($curl->downloads === 2, 'Each of two unknown source identities must download only once.');
    $assert($files->copies === 1, 'Identical source content created more than one permanent copy.');
    $assert($files->targetChecks === 1, 'Repeated products checked the same target again within a batch.');
    $assert($localFiles->hashes === 1, 'A verified local file was rehashed within the same batch.');
    foreach ($ids as $id) {
        $assert($repository->galleryUsages($id)[0]['attached_path'] === $firstPath, 'Products do not share one path.');
        foreach (['image', 'small_image', 'thumbnail'] as $role) {
            $assert($productResource->getAttributeRawValue($id, $role, 0)
                === '/' . substr($firstPath, strlen('catalog/product/')), 'An image role has an incorrect path.');
        }
    }
    $native = '/' . substr($firstPath, strlen('catalog/product/'));
    $valueId = (int)$db->fetchOne($db->select()->from($resource->getTableName('catalog_product_entity_media_gallery'),
        ['value_id'])->where('value = ?', $native));
    $assert((int)$db->fetchOne($db->select()->from(
        $resource->getTableName('catalog_product_entity_media_gallery_value_to_entity'), ['COUNT(*)'])
        ->where('value_id = ?', $valueId)) === 3, 'The shared gallery value is not linked to all three products.');
    $initialMetrics = ['http_downloads' => $curl->downloads, 'permanent_copies' => $files->copies,
        'target_checks' => $files->targetChecks, 'local_hashes' => $localFiles->hashes];

    $asset = $repository->ensureAsset($sourcePath);
    unlink($root . '/var/' . $asset->cachePath);
    $cache->run(fn (): string => $materializer->shared($asset, 'catalog/product/ergonode/shared'));
    $assert($curl->downloads === 2, 'Removing the source cache downloaded an existing target again.');
    $assert($files->targetChecks === 2, 'A new batch did not recheck the existing target once.');

    $repository->recordStream([$item($sourcePath, 'http://' . $address . '/replacement.png', 'replacement')]);
    $cache->run(function () use ($gallery, $ids): void {
        $gallery->process($ids[0]);
        $gallery->process($ids[1]);
    });
    $replacement = $repository->galleryUsages($ids[0])[0]['attached_path'];
    $assert($replacement !== $firstPath, 'A new source revision reused the old content path.');
    $assert($repository->galleryUsages($ids[1])[0]['attached_path'] === $replacement,
        'Products referencing the replaced source did not share its new path.');
    $assert($repository->galleryUsages($ids[2])[0]['attached_path'] === $firstPath,
        'Replacing one source changed the independent alias source.');
    $assert($curl->downloads === 3 && $files->copies === 2, 'A changed asset was downloaded or copied repeatedly.');
    $assert(is_file($root . '/media/' . $firstPath), 'Replacing a source removed a file still used by another product.');
    $replacementMetrics = ['http_downloads' => $curl->downloads, 'permanent_copies' => $files->copies];

    unlink($root . '/media/' . $replacement);
    $cache->run(fn (): string => $materializer->shared($repository->ensureAsset($sourcePath),
        'catalog/product/ergonode/shared'));
    $assert(is_file($root . '/media/' . $replacement), 'The next batch did not restore a missing target.');
    $assert($curl->downloads === 3 && $files->copies === 3,
        'Recovering a missing target did not reuse the available source cache.');

    $manual = 'catalog/product/manual/' . $prefix . '.png';
    mkdir(dirname($root . '/media/' . $manual), 0777, true);
    $manualBytes = $png . $prefix . '-manual';
    file_put_contents($root . '/media/' . $manual, $manualBytes);
    file_put_contents($root . '/fixtures/replacement.png', $manualBytes);
    $stat = $localFiles->stat($manual);
    $om->get(\Ergonode\Media\Model\Port\LocalFileIndexInterface::class)->save(
        $manual, hash('sha256', $manualBytes, true), $stat['size'], $stat['modified_at']);
    $manualSource = '/' . $prefix . '/manual.png';
    $repository->recordStream([$item($manualSource, 'http://' . $address . '/replacement.png', 'manual')]);
    $reused = $cache->run(fn (): string => $materializer->shared($repository->ensureAsset($manualSource),
        'catalog/product/ergonode/shared'));
    $assert($reused === $manual && $curl->downloads === 4 && $files->copies === 3,
        'An indexed existing image was copied instead of reusing its original path.');

    // A worker prepared old data before a newer task changed the desired gallery.
    $staleWork = $repository->claim(1, 300)[0];
    $staleGalleryWrite = $gallery->prepare($staleWork->productId);
    $repository->replaceGallery($staleWork->productId, [$aliasPath]);
    $freshWork = $repository->claim(1, 300)[0];
    $assert($freshWork->productId === $staleWork->productId, 'The replacement task was not claimed.');
    $worker->process($freshWork);
    $repository->complete($freshWork);
    $assert(!$repository->applyWork($staleWork, $staleGalleryWrite), 'The stale worker was allowed to write.');
    $assert($repository->galleryUsages($staleWork->productId)[0]['attached_path'] === $firstPath,
        'The stale worker replaced the current attachment.');
    $assert($productResource->getAttributeRawValue($staleWork->productId, 'image', 0)
        === '/' . substr($firstPath, strlen('catalog/product/')), 'The stale worker replaced the current image role.');

    // A download started at one revision must not activate after the stream advances.
    $revisionPath = '/' . $prefix . '/revision.png';
    $revisionAsset = $repository->ensureAsset($revisionPath);
    $repository->recordStream([$item($revisionPath, 'http://' . $address . '/first.png', 'revision-next')]);
    $activationRejected = false;
    try {
        $repository->activate($revisionAsset->id, hash('sha256', 'old', true), 'old.png', 3, $revisionAsset->revision);
    } catch (\Magento\Framework\Exception\LocalizedException) {
        $activationRejected = true;
    }
    $assert($activationRejected, 'Old downloaded content activated over a newer source revision.');
    $assert($repository->getAsset($revisionAsset->id)->status === 'dirty', 'New revision lost its dirty state.');

    // Exercise the actual consumer boundary; its cache must not outlive one message.
    $consumer = new Consumer($repository, $worker, $publisher,
        $om->get(\Ergonode\Media\Model\Config\MediaConfig::class), $config,
        $om->get(\Psr\Log\LoggerInterface::class),
        $om->get(\Ergonode\Media\Model\Index\ScanReadiness::class), $cache);
    $consumer->process('drain');
    $assert(!$repository->hasWork(), 'The consumer did not finish the fixture work.');
    $assert($curl->downloads === 4, 'The consumer downloaded already resolved sources.');
    $report = ['success' => true, 'products' => 3, 'repeated_resolutions' => 100,
        'initial_import' => $initialMetrics, 'after_replacement' => $replacementMetrics, 'final_totals' => [
            'http_downloads' => $curl->downloads, 'permanent_copies' => $files->copies,
        ], 'missing_target_restored_without_download' => true, 'indexed_existing_file_reused' => true,
        'stale_worker_rejected' => true, 'stale_source_activation_rejected' => true];
} finally {
    $db->rollBack();
    if (is_resource($server)) {
        proc_terminate($server);
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) { fclose($pipe); }
        }
        proc_close($server);
    }
    (new File())->rmdir($root, true);
}
foreach ($initialCounts as $table => $count) {
    $assert((int)$db->fetchOne('SELECT COUNT(*) FROM ' . $resource->getTableName($table)) === $count,
        'The smoke test left rows in ' . $table);
}
echo json_encode($report + ['assertions' => $assertions], JSON_PRETTY_PRINT) . PHP_EOL;
echo 'Fixture database rows and files removed.' . PHP_EOL;
