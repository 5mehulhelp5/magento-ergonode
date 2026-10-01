<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\E2e;

use Ergonode\ProductPublisher\Model\Source\ProductSourceLoader;
use Ergonode\ProductPublisher\Test\E2e\Support\CohortPlanner;
use Ergonode\ProductPublisher\Test\E2e\Support\LocalStateCoordinator;
use Ergonode\ProductPublisher\Test\E2e\Support\RemoteFixtureProvisioner;
use Ergonode\ProductPublisher\Test\E2e\Support\SampleDataManifestFactory;
use Ergonode\ProductPublisher\Test\E2e\Support\ScenarioConfiguration;
use Ergonode\ProductPublisher\Test\E2e\Support\UserRestClient;
use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\ProductPublisher\Api\Data\ProductSynchronizationResultInterface;
use Ergonode\ProductPublisher\Api\ProductBatchSynchronizerInterface;
use Ergonode\ProductPublisher\Api\ProductPublicationSynchronizerInterface;
use Ergonode\ProductPublisher\Api\ProductSynchronizerInterface;
use Ergonode\TemplateConsumer\Api\TemplateSynchronizerInterface;
use Ergonode\TemplatePublisher\Api\TemplateCreatorInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

class LiveProductPublicationJourneyTest extends TestCase
{
    public function testSampleDataCatalogConvergesFromDeterministicDegradedState(): void
    {
        $configuration = ScenarioConfiguration::fromEnvironment();
        $bootstrap = Bootstrap::create(dirname(__DIR__, 5), $_SERVER);
        $objectManager = $bootstrap->getObjectManager();
        try {
            $objectManager->get(State::class)->setAreaCode(Area::AREA_ADMINHTML);
        } catch (Throwable) {
            // The bootstrap may already have established the admin area.
        }

        $endpoint = $objectManager->get(ConfigProvider::class)->getGraphQlUrl();
        $parts = parse_url($endpoint);
        self::assertIsArray($parts, 'Configure the Ergonode GraphQL endpoint before the live E2E run.');
        self::assertNotEmpty($parts['scheme'] ?? null);
        self::assertNotEmpty($parts['host'] ?? null);
        $origin = (string)$parts['scheme'] . '://' . (string)$parts['host'];
        if (isset($parts['port'])) {
            $origin .= ':' . (int)$parts['port'];
        }
        $language = $objectManager
            ->get(LanguageStoreMappingProviderInterface::class)
            ->requireAdminLanguageCode();
        $client = new UserRestClient(
            $objectManager->create(Curl::class),
            $objectManager->get(Json::class),
            $origin,
            $language
        );
        $client->login($configuration->getUsername(), $configuration->getPassword());

        $manifest = $objectManager->create(SampleDataManifestFactory::class)->create(
            $configuration->getSeed(),
            $configuration->getProductLimit()
        );
        $this->writeArtifact($manifest, 'manifest.json');
        $selectedSkus = [];
        foreach ((array)$manifest['products'] as $product) {
            if (!empty($product['selected'])) {
                $selectedSkus[] = (string)$product['sku'];
            }
        }
        self::assertGreaterThanOrEqual(7, count($selectedSkus));
        foreach ($selectedSkus as $sku) {
            self::assertNull(
                $client->findByField('products', 'sku', $sku),
                'The live E2E requires an isolated tenant without the selected Sample Data SKU: ' . $sku
            );
        }

        $local = $objectManager->create(LocalStateCoordinator::class);
        $remote = new RemoteFixtureProvisioner(
            $client,
            $objectManager->get(TemplateCreatorInterface::class),
            $objectManager->get(ResourceConnection::class),
            $language
        );
        $report = ['manifest' => $this->manifestSummary($manifest)];
        $cleanup = !$configuration->shouldKeepFixtures();

        try {
            $report['product_first'] = $this->assertProductSourceIsInitiallyBlocked(
                $objectManager->get(ProductSourceLoader::class),
                $selectedSkus
            );

            $initial = [CohortPlanner::BOTH, CohortPlanner::PUBLISHED_ONLY, CohortPlanner::MAPPED_ONLY];
            $mapped = [CohortPlanner::BOTH, CohortPlanner::MAPPED_ONLY];
            $missingAttributes = $this->missingRemoteCodes($client, 'attributes', $manifest['attributes']);
            $remote->expectCreatedAttributes($missingAttributes);
            $local->saveAttributeMappings($manifest, $initial, $missingAttributes);
            $report['imports']['attributes_initial'] = $local->importAttributes(true);
            foreach ($missingAttributes as $code) {
                $remote->registerCreatedAttribute($code);
            }
            $local->saveOptionMappings($manifest, $initial);
            $report['imports']['attributes_options'] = $local->importAttributes(true);
            $local->saveAttributeMappings($manifest, $mapped);

            $remote->provisionCategoriesAndTrees($manifest, $initial);
            $local->configureAndImportCategories($manifest, $mapped);
            $remote->provisionCategoriesAndTrees(
                $manifest,
                [CohortPlanner::BOTH, CohortPlanner::PUBLISHED_ONLY]
            );
            $remote->removeMappedOnlyCategoriesAndTrees($manifest);
            $remote->provisionTemplates($manifest, $initial);
            $report['imports']['templates_initial'] = $local->importTemplates(true);
            $local->saveTemplateMappings($manifest, $mapped);
            $remote->removeMappedOnlyTemplates($manifest);

            $report['degraded_products'] = $this->assertDegradedProductPublication(
                $objectManager->get(ProductSourceLoader::class),
                $objectManager->get(ProductBatchSynchronizerInterface::class),
                $client,
                $remote,
                $selectedSkus
            );
            $remote->removeMappedOnlyAttributes($manifest);
            $report['stale_attribute_products'] = $this->assertAttributeReferenceFailure(
                $objectManager->get(ProductSourceLoader::class),
                $objectManager->get(ProductBatchSynchronizerInterface::class),
                $selectedSkus
            );

            $all = [
                CohortPlanner::BOTH,
                CohortPlanner::PUBLISHED_ONLY,
                CohortPlanner::MAPPED_ONLY,
                CohortPlanner::NEITHER,
            ];
            $missingAttributes = $this->missingRemoteCodes($client, 'attributes', $manifest['attributes']);
            $remote->expectCreatedAttributes($missingAttributes);
            $local->saveAttributeMappings($manifest, $all, $missingAttributes);
            $report['imports']['attributes_complete'] = $local->importAttributes(true);
            foreach ($missingAttributes as $code) {
                $remote->registerCreatedAttribute($code);
            }
            $local->saveOptionMappings($manifest, $all);
            $report['imports']['attributes_complete_options'] = $local->importAttributes(true);

            $remote->provisionCategoriesAndTrees($manifest, $all);
            $local->configureAndImportCategories($manifest, $all);
            $remote->provisionTemplates($manifest, $all);
            $report['imports']['templates_complete'] = $local->importTemplates(true);
            $local->saveTemplateMappings($manifest, $all);

            $synchronizer = $objectManager->get(ProductPublicationSynchronizerInterface::class);
            $first = $synchronizer->synchronize($selectedSkus);
            self::assertSame('', $this->productFailureMessage($first));
            $report['happy_path'] = $this->productStats($first);

            foreach ($selectedSkus as $sku) {
                $remote->registerCreatedProduct($sku);
            }
            $second = $synchronizer->synchronize($selectedSkus);
            self::assertSame('', $this->productFailureMessage($second));
            foreach ($second as $item) {
                self::assertSame(
                    ProductSynchronizationResultInterface::STATUS_NOOP,
                    $item->getStatus(),
                    sprintf('Second run mutated product "%s".', $item->getSku())
                );
            }
            $report['second_run'] = $this->productStats($second);
            $report['template_snapshot'] = $this->assertTemplateListRefreshesWholeSnapshot($objectManager);
        } finally {
            try {
                $this->writeArtifact($report, 'report.json');
            } finally {
                if ($cleanup) {
                    try {
                        $remote->cleanup();
                    } finally {
                        $local->cleanup();
                    }
                }
            }
        }
    }

    /** @param string[] $selectedSkus @return array<string, int> */
    private function assertProductSourceIsInitiallyBlocked(ProductSourceLoader $loader, array $selectedSkus): array
    {
        $result = $loader->load($selectedSkus);
        self::assertFalse($result->isAuthoritative());
        self::assertCount(count($selectedSkus), $result->getSkippedProductWarnings());
        self::assertSame([], $result->getStates());

        return ['selected' => count($selectedSkus), 'skipped' => count($result->getSkippedProductWarnings())];
    }

    /** @param string[] $selectedSkus @return array<string, int> */
    private function assertDegradedProductPublication(
        ProductSourceLoader $loader,
        ProductBatchSynchronizerInterface $synchronizer,
        UserRestClient $client,
        RemoteFixtureProvisioner $remote,
        array $selectedSkus
    ): array {
        $source = $loader->load($selectedSkus);
        $selected = array_fill_keys($selectedSkus, true);
        $states = array_values(array_filter(
            $source->getStates(),
            static fn ($state): bool => isset($selected[$state->getSku()])
        ));
        self::assertNotEmpty($states);
        self::assertNotEmpty($source->getSkippedProductWarnings());
        $absentBefore = [];
        foreach ($states as $state) {
            if ($client->findByField('products', 'sku', $state->getSku()) === null) {
                $absentBefore[$state->getSku()] = true;
            }
        }
        $results = $synchronizer->synchronizeBatch(
            $states,
            ProductSynchronizerInterface::MODE_UPDATE
        );
        $statuses = [];
        foreach ($results as $result) {
            $sku = $result->getSku();
            $statuses[$result->getStatus()] = ($statuses[$result->getStatus()] ?? 0) + 1;
            if (isset($absentBefore[$sku])
                && $result->getStatus() === ProductSynchronizationResultInterface::STATUS_BLOCKED_REFERENCE
            ) {
                self::assertNull(
                    $client->findByField('products', 'sku', $sku),
                    'A reference-blocked product was partially created: ' . $sku
                );
            }
            if (isset($absentBefore[$sku])
                && in_array($result->getStatus(), [
                    ProductSynchronizationResultInterface::STATUS_SUCCESS,
                    ProductSynchronizationResultInterface::STATUS_NOOP,
                ], true)
            ) {
                $remote->registerCreatedProduct($sku);
            }
        }
        self::assertGreaterThan(0, $statuses[ProductSynchronizationResultInterface::STATUS_BLOCKED_REFERENCE] ?? 0);
        self::assertGreaterThan(0, ($statuses[ProductSynchronizationResultInterface::STATUS_SUCCESS] ?? 0)
            + ($statuses[ProductSynchronizationResultInterface::STATUS_NOOP] ?? 0));

        return $statuses + ['source_skipped' => count($source->getSkippedProductWarnings())];
    }

    /** @param string[] $selectedSkus @return array<string, int> */
    private function assertAttributeReferenceFailure(
        ProductSourceLoader $loader,
        ProductBatchSynchronizerInterface $synchronizer,
        array $selectedSkus
    ): array {
        $selected = array_fill_keys($selectedSkus, true);
        $states = array_values(array_filter(
            $loader->load($selectedSkus)->getStates(),
            static fn ($state): bool => isset($selected[$state->getSku()])
        ));
        $results = $synchronizer->synchronizeBatch($states, ProductSynchronizerInterface::MODE_UPDATE);
        $statuses = [];
        foreach ($results as $result) {
            $statuses[$result->getStatus()] = ($statuses[$result->getStatus()] ?? 0) + 1;
        }
        self::assertGreaterThan(
            0,
            $statuses[ProductSynchronizationResultInterface::STATUS_BLOCKED_REFERENCE] ?? 0
        );

        return $statuses;
    }

    /** @return array<string, int|string> */
    private function assertTemplateListRefreshesWholeSnapshot($objectManager): array
    {
        $synchronizer = $objectManager->get(TemplateSynchronizerInterface::class);
        $first = $synchronizer->execute(true);
        $second = $synchronizer->execute();
        self::assertGreaterThan(0, (int)($first['events'] ?? 0));
        self::assertSame($first['events'], $second['events']);
        self::assertNull($first['cursor'] ?? null);
        self::assertNull($second['cursor'] ?? null);
        self::assertSame(0, (int)($second['imported'] ?? -1));
        self::assertSame((int)$second['events'], (int)($second['unchanged'] ?? -1));

        return [
            'status' => 'verified',
            'first_templates' => (int)($first['events'] ?? 0),
            'second_templates' => (int)($second['events'] ?? 0),
        ];
    }

    /** @param array<string, array<string, mixed>> $entities @return string[] */
    private function missingRemoteCodes(UserRestClient $client, string $resource, array $entities): array
    {
        $missing = [];
        foreach ($entities as $entity) {
            $code = (string)$entity['remote_code'];
            if ($client->findByCode($resource, $code) === null) {
                $missing[] = $code;
            }
        }

        return $missing;
    }

    /** @param ProductSynchronizationResultInterface[] $items @return array<string, int> */
    private function productStats(array $items): array
    {
        $stats = [];
        foreach ($items as $item) {
            $key = 'products:' . $item->getStatus();
            $stats[$key] = ($stats[$key] ?? 0) + 1;
        }
        ksort($stats);

        return $stats;
    }

    /** @param ProductSynchronizationResultInterface[] $items */
    private function productFailureMessage(array $items): string
    {
        $messages = [];
        foreach ($items as $item) {
            if (in_array($item->getStatus(), [
                ProductSynchronizationResultInterface::STATUS_FAILED,
                ProductSynchronizationResultInterface::STATUS_BLOCKED_REFERENCE,
            ], true)) {
                $messages[] = sprintf(
                    'product %s: %s',
                    $item->getSku(),
                    (string)$item->getMessage()
                );
            }
        }

        return implode(PHP_EOL, $messages);
    }

    /** @param array<string, mixed> $manifest @return array<string, int> */
    private function manifestSummary(array $manifest): array
    {
        return [
            'products' => count((array)$manifest['products']),
            'attributes' => count((array)$manifest['attributes']),
            'categories' => count((array)$manifest['categories']),
            'roots' => count((array)$manifest['roots']),
            'templates' => count((array)$manifest['templates']),
        ];
    }

    /** @param array<string, mixed> $data */
    private function writeArtifact(array $data, string $filename): void
    {
        $directory = dirname(__DIR__, 5) . '/dev/tests/ergonode-e2e/.artifacts';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create Ergonode E2E artifact directory.');
        }
        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (file_put_contents($directory . '/' . $filename, $encoded . PHP_EOL) === false) {
            throw new RuntimeException('Unable to write Ergonode E2E artifact: ' . $filename);
        }
    }
}
