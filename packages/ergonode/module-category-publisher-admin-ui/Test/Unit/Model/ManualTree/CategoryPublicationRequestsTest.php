<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Test\Unit\Model\ManualTree;

use Ergonode\Category\Model\CategoryTree\CategoryTreeQuery;
use Ergonode\Category\Model\Import\CategoryDetailsLoader;
use Ergonode\Category\Model\GraphQl\CategoryQueries;
use Ergonode\Category\Model\Import\CategoryNormalizer;
use Ergonode\Category\Model\Provider\CategoryCacheProvider;
use Ergonode\Category\Model\Snapshot\CategorySnapshotWriter;
use Ergonode\Category\Model\Snapshot\CategoryTreeSnapshotUpdater;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryTreePublisher;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryTreePayloadBuilder;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\PendingCategoryPublisher;
use PHPUnit\Framework\Attributes\DataProvider;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryPublicationDetails;
use Ergonode\CategoryPublisherAdminUi\Model\ManualRest\CategoryTreeIdentityCache;
use Ergonode\Category\Api\CategoryRemoteIdentityProviderInterface;
use Ergonode\Category\Api\CategoryRemoteIdentityWriterInterface;
use Ergonode\CategoryPublisher\Model\Data\CategoryDesiredStateFactory;
use Ergonode\CategoryPublisher\Model\GraphQl\CategoryMutationFactory;
use Ergonode\CategoryPublisher\Model\Sync\CategoryStateLoader;
use Ergonode\CategoryPublisher\Model\Sync\CategorySynchronizer;
use Ergonode\CategoryPublisher\Model\Sync\CategorySyncPlanner;
use Ergonode\CategoryPublisher\Model\Sync\StrictCategoryCreator;
use Ergonode\CategoryPublisherAdminUi\Model\ManualRest\CategoryTreeGateway;
use Ergonode\CategoryPublisherAdminUi\Model\ManualRest\Client;
use Ergonode\CategoryPublisherAdminUi\Model\ManualRest\EndpointResolver;
use Ergonode\CategoryPublisherAdminUi\Model\ManualRest\RetryableRequestException;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryBatchPublisher;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryCreationCollisionLogger;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryCreationStateBuilder;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryPublicationCheckpoint;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryRemoteIdentityResolver;
use Ergonode\Core\Api\GraphQlMutationClientInterface;
use Ergonode\Core\Api\GraphQlWriteScopeQueryClientInterface;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\Publisher\Api\RetryDelayInterface;
use Ergonode\Publisher\Model\GraphQl\MutationAliasGenerator;
use Ergonode\Publisher\Model\GraphQl\MutationBatchBuilder;
use Ergonode\Publisher\Model\GraphQl\MutationBatchPlanner;
use Ergonode\Publisher\Model\GraphQl\MutationErrorMapper;
use Ergonode\Publisher\Model\GraphQl\MutationExecutor;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class CategoryPublicationRequestsTest extends TestCase
{
    private bool $numericCodes = false;
    private CategoryPublicationDetails $details;
    private CategoryTreeIdentityCache $treeCache;
    private CategoryTreeGateway $gateway;
    private CategoryRemoteIdentityResolver $resolver;

    #[DataProvider('treeIdentityStates')]
    public function testFullPublicationUsesFourRequestsWithCachedTreeIdentity(bool $cached, bool $numericCodes): void
    {
        $this->numericCodes = $numericCodes;
        $rest = $this->createMock(Client::class);
        $rest->expects(self::exactly($cached ? 2 : 3))->method('get')->willReturnCallback(
            function (string $resource): array {
                if (str_starts_with($resource, 'categories?')) {
                    return $this->remoteRows();
                }
                if (str_starts_with($resource, 'trees?')) {
                    return ['collection' => [['code' => 'main', 'id' => 'tree-id']]];
                }
                self::assertSame('trees/tree-id', $resource);
                return ['id' => 'tree-id', 'code' => 'main', 'categories' => []];
            }
        );
        $rest->expects(self::once())->method('put')->with('trees/tree-id', self::callback(
            static function (array $payload): bool {
                self::assertCount(50, $payload['categories']);
                return true;
            }
        ))->willReturn([]);
        $publisher = $this->publisher($rest);
        if ($cached) {
            $this->treeCache->save('main', 'tree-id');
        }
        $items = $this->items();
        $publisher->publish(7, $items);
        self::assertCount(50, $this->details->get(7));
        foreach ($items as &$item) {
            $item['extension_data']['to_ergonode']['remote_prepared'] = true;
        }
        unset($item);
        $read = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $read->expects(self::never())->method('queryWriteScope');
        $languages = $this->createMock(LanguageStoreMappingProviderInterface::class);
        $languages->expects(self::never())->method('getLanguageCodes');
        $cache = $this->createMock(CategoryCacheProvider::class);
        $cache->expects(self::once())->method('getRowsByCode')->with(7)->willReturn([]);
        $cache->expects(self::once())->method('clearCache');
        $writer = $this->createMock(CategorySnapshotWriter::class);
        $writer->expects(self::once())->method('replaceCompleteSnapshot')->with(7, self::callback(
            static function (array $rows): bool {
                self::assertCount(50, $rows);
                foreach ($rows as $index => $row) {
                    self::assertSame(['pl_PL' => 'Category ' . ($index + 1)], $row['labels']);
                }
                return true;
            }
        ));
        $snapshot = new CategoryTreeSnapshotUpdater(
            $cache,
            new CategoryDetailsLoader($read, $languages, new CategoryQueries()),
            new CategoryNormalizer(new Json()),
            $writer
        );
        $treeQuery = $this->createStub(CategoryTreeQuery::class);
        $treeQuery->method('getById')->willReturn(['tree_code' => 'main']);
        (new CategoryTreePublisher(
            $cache,
            $treeQuery,
            $this->gateway,
            new CategoryTreePayloadBuilder(),
            $snapshot,
            $this->resolver,
            $this->createStub(PendingCategoryPublisher::class),
            $this->details
        ))->publish(7, $items);
        self::assertSame([], $this->details->get(7));
    }

    public static function treeIdentityStates(): array
    {
        return [[true, false], [false, false], [true, true], [false, true]];
    }

    public function testCompleteFiftyCategoryBatchUsesOneMutationAndOneRestRequest(): void
    {
        $rest = $this->createMock(Client::class);
        $rest->expects(self::once())->method('get')->willReturn($this->remoteRows());
        $results = $this->publisher($rest)->publish(7, $this->items());
        self::assertCount(50, $results);
        foreach ($results as $result) {
            self::assertSame('synchronized', $result['status']);
            self::assertSame('id_' . $result['code'], $result['remote_id']);
        }
    }

    public function testRestRateLimitDoesNotReplaySuccessfulFiftyCategoryMutation(): void
    {
        $rest = $this->createMock(Client::class);
        $attempt = 0;
        $rows = $this->remoteRows();
        $rest->expects(self::exactly(2))->method('get')->willReturnCallback(
            static function () use (&$attempt, $rows): array {
                if (++$attempt === 1) {
                    throw new RetryableRequestException('Rate limited.', 7);
                }
                return $rows;
            }
        );
        $publisher = $this->publisher($rest);
        $this->expectFirstAttemptToWait($publisher);
        self::assertCount(50, $this->details->get(7));
        self::assertCount(50, $publisher->publish(7, $this->items()));
        self::assertCount(50, $this->details->get(7));
    }

    public function testRetryOnlyReadsUnresolvedIdAndKeepsFortyNineSavedIds(): void
    {
        $rest = $this->createMock(Client::class);
        $rows = $this->remoteRows();
        array_pop($rows['collection']);
        $rows['collection'][] = ['code' => 'unrelated', 'id' => 'other'];
        $attempt = 0;
        $rest->expects(self::exactly(3))->method('get')->willReturnCallback(
            static function (string $resource) use (&$attempt, $rows): array {
                if (++$attempt === 1) {
                    return $rows;
                }
                self::assertStringContainsString('filter=code%3Dc_50', $resource);
                if ($attempt === 2) {
                    throw new RetryableRequestException('Rate limited.', 7);
                }
                return ['collection' => [['code' => 'c_50', 'id' => 'id_c_50']]];
            }
        );
        $publisher = $this->publisher($rest);
        $this->expectFirstAttemptToWait($publisher);
        self::assertCount(50, $this->details->get(7));
        self::assertCount(50, $publisher->publish(7, $this->items()));
        self::assertCount(50, $this->details->get(7));
    }

    private function expectFirstAttemptToWait(CategoryBatchPublisher $publisher): void
    {
        try {
            $publisher->publish(7, $this->items());
            self::fail('The first REST attempt should be rate limited.');
        } catch (RetryableRequestException $exception) {
            self::assertSame(7, $exception->getRetryAfterSeconds());
        }
    }

    private function publisher(Client $rest): CategoryBatchPublisher
    {
        $read = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $read->expects(self::never())->method('queryWriteScope');
        $write = $this->createMock(GraphQlMutationClientInterface::class);
        $write->expects(self::once())->method('mutateWithResponse')->willReturnCallback(
            static function (string $document, array $variables): array {
                self::assertSame(50, substr_count($document, ': categoryCreate('));
                self::assertSame(50, substr_count($document, 'name {'));
                $data = [];
                foreach ($variables as $name => $input) {
                    $data[substr($name, 0, -6)] = ['category' => ['code' => $input['code'], 'name' => $input['name']]];
                }
                return ['data' => $data];
            }
        );
        $loader = new CategoryStateLoader($read);
        $factory = new CategoryMutationFactory();
        $builder = new MutationBatchBuilder(new MutationAliasGenerator(), new Json());
        $planner = new MutationBatchPlanner($builder);
        $executor = new MutationExecutor(
            $write,
            $builder,
            new MutationErrorMapper(),
            $this->createStub(RetryDelayInterface::class)
        );
        $synchronizer = new CategorySynchronizer(
            $loader,
            new CategorySyncPlanner($factory),
            $planner,
            $executor,
            new StrictCategoryCreator($loader, $factory, $planner, $executor)
        );
        $ids = [];
        $provider = $this->createStub(CategoryRemoteIdentityProviderInterface::class);
        $provider->method('getIdsByCode')->willReturnCallback(static function () use (&$ids): array {
            return $ids;
        });
        $writer = $this->createStub(CategoryRemoteIdentityWriterInterface::class);
        $writer->method('saveRemoteIdentities')->willReturnCallback(
            static function ($treeId, $items) use (&$ids): void {
                self::assertSame(7, $treeId);
                foreach ($items as $item) {
                    $ids[$item['code']] = $item['remote_id'];
                }
            }
        );
        $language = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $language->method('requireAdminLanguageCode')->willReturn('pl_PL');

        $session = $this->session();
        $endpoint = $this->createStub(EndpointResolver::class);
        $endpoint->method('loginUrl')->willReturn('https://example.test/api/v1/login');
        $this->details = new CategoryPublicationDetails($session, $endpoint);
        $this->treeCache = new CategoryTreeIdentityCache($session, $endpoint);
        $this->gateway = new CategoryTreeGateway($rest, $this->treeCache);
        $this->resolver = new CategoryRemoteIdentityResolver($provider, $writer, $this->gateway);

        return new CategoryBatchPublisher(
            new CategoryCreationStateBuilder(new CategoryDesiredStateFactory(), $language),
            $synchronizer,
            $this->resolver,
            $this->createStub(CategoryCreationCollisionLogger::class),
            new CategoryPublicationCheckpoint($session, $endpoint),
            $this->details
        );
    }

    private function session(): Session
    {
        $stored = [];
        $session = $this->createStub(Session::class);
        $session->method('getData')->willReturnCallback(static function (string $key) use (&$stored): mixed {
            return $stored[$key] ?? null;
        });
        $session->method('__call')->willReturnCallback(
            static function (string $method, array $args) use (&$stored, $session): Session {
                self::assertContains($method, ['setData', 'unsetData']);
                $stored[$args[0]] = $method === 'setData' ? $args[1] : null;
                return $session;
            }
        );
        return $session;
    }

    /** @return array<int, array<string, mixed>> */
    private function items(): array
    {
        $items = [];
        for ($index = 1; $index <= 50; ++$index) {
            $items[] = [
                'code' => $this->numericCodes ? (string)($index - 1) : 'c_' . $index,
                'label' => 'Category ' . $index,
                'extension_data' => ['to_ergonode' => ['pending_create' => true]],
            ];
        }
        return $items;
    }

    /** @return array{collection: array<int, array{code: string, id: string}>} */
    private function remoteRows(): array
    {
        return ['collection' => array_map(static fn (array $item): array => [
            'code' => $item['code'], 'id' => 'id_' . $item['code'],
        ], $this->items())];
    }
}
