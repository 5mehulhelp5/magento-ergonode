<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Test\Unit\Model\ManualTree;

use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryPublicationDetails;
use Ergonode\Category\Api\CategoryRemoteIdentityProviderInterface;
use Ergonode\Category\Api\CategoryRemoteIdentityWriterInterface;
use Ergonode\CategoryPublisher\Api\CategoryBatchSynchronizerInterface;
use Ergonode\CategoryPublisher\Api\CategoryDesiredStateFactoryInterface;
use Ergonode\CategoryPublisher\Api\CategorySynchronizerInterface;
use Ergonode\CategoryPublisher\Api\Data\CategoryStateInterface;
use Ergonode\CategoryPublisher\Api\Data\CategorySynchronizationResultInterface;
use Ergonode\CategoryPublisher\Model\Data\CategorySynchronizationResult;
use Ergonode\CategoryPublisherAdminUi\Model\ManualRest\CategoryTreeGateway;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryBatchPublisher;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryPublicationCheckpoint;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryCreationCollisionLogger;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryCreationStateBuilder;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryRemoteIdentityResolver;
use Ergonode\Core\Api\Exception\GraphQlRequestException;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\Publisher\Api\Data\MutationOperationInterface;
use Ergonode\Publisher\Api\Data\MutationResultInterface;
use Ergonode\Publisher\Model\Data\MutationResult;
use PHPUnit\Framework\TestCase;

class CategoryBatchPublisherTest extends TestCase
{
    public function testPersistsSuccessfulSiblingWhenAnotherItemIsInvalid(): void
    {
        $state = $this->createStub(CategoryStateInterface::class);
        $state->method('getCode')->willReturn('chairs');
        $stateFactory = $this->createMock(CategoryDesiredStateFactoryInterface::class);
        $stateFactory->expects(self::once())
            ->method('createCategory')
            ->with('chairs', ['pl_PL' => 'Krzesła'])
            ->willReturn($state);
        $synchronizer = $this->createMock(CategoryBatchSynchronizerInterface::class);
        $synchronizer->expects(self::once())
            ->method('synchronizeBatch')
            ->with([$state], CategorySynchronizerInterface::MODE_CREATE_STRICT)
            ->willReturn([
                'chairs' => new CategorySynchronizationResult(
                    CategorySynchronizationResultInterface::STATUS_SUCCESS,
                    CategorySynchronizationResultInterface::REFERENCE_PRESENT
                ),
            ]);
        $identities = $this->createMock(CategoryRemoteIdentityProviderInterface::class);
        $identities->method('getIdsByCode')->with(7, ['chairs'])->willReturn([]);
        $writer = $this->createMock(CategoryRemoteIdentityWriterInterface::class);
        $writer->expects(self::once())->method('saveRemoteIdentities')->with(7, [[
            'code' => 'chairs',
            'remote_id' => 'remote-chairs',
            'manual_parent_code' => 'furniture',
            'manual_sort_order' => 4,
            'magento_category_id' => 12,
        ]]);
        $gateway = $this->createMock(CategoryTreeGateway::class);
        $gateway->expects(self::once())->method('getCategoryIds')
            ->with(['chairs'])->willReturn(['chairs' => 'remote-chairs']);

        $results = $this->publisher($stateFactory, $synchronizer, $identities, $writer, $gateway)->publish(7, [
            $this->item('Invalid-Code', 'Niepoprawna'),
            $this->item('chairs', 'Krzesła', 'furniture', 4, 12),
        ]);

        self::assertSame('failed', $results[0]['status']);
        self::assertStringContainsString('Invalid Ergonode category code', $results[0]['message']);
        self::assertSame('synchronized', $results[1]['status']);
        self::assertSame('remote-chairs', $results[1]['remote_id']);
    }

    public function testCreatesFirstCategoryAndSkipsDuplicateReadableCodeWithWarning(): void
    {
        $state = $this->createStub(CategoryStateInterface::class);
        $state->method('getCode')->willReturn('promocja_10_99');
        $stateFactory = $this->createMock(CategoryDesiredStateFactoryInterface::class);
        $stateFactory->expects(self::once())->method('createCategory')->willReturn($state);
        $synchronizer = $this->createMock(CategoryBatchSynchronizerInterface::class);
        $synchronizer->expects(self::once())
            ->method('synchronizeBatch')
            ->with([$state], CategorySynchronizerInterface::MODE_CREATE_STRICT)
            ->willReturn([
                'promocja_10_99' => new CategorySynchronizationResult(
                    CategorySynchronizationResultInterface::STATUS_SUCCESS,
                    CategorySynchronizationResultInterface::REFERENCE_PRESENT
                ),
            ]);
        $identities = $this->createStub(CategoryRemoteIdentityProviderInterface::class);
        $identities->method('getIdsByCode')->willReturn([]);
        $writer = $this->createStub(CategoryRemoteIdentityWriterInterface::class);
        $gateway = $this->createStub(CategoryTreeGateway::class);
        $gateway->method('getCategoryIds')->willReturn(['promocja_10_99' => 'remote-promotion']);
        $collisionLogger = $this->createMock(CategoryCreationCollisionLogger::class);
        $collisionLogger->expects(self::once())->method('warning')->with(
            7,
            'promocja_10_99',
            11,
            'Promocja 10-99',
            'batch',
            10,
            'Promocja 10.99'
        );

        $results = $this->publisher(
            $stateFactory,
            $synchronizer,
            $identities,
            $writer,
            $gateway,
            $collisionLogger
        )->publish(7, [
            $this->item('promocja_10_99', 'Promocja 10.99', null, 1, 10),
            $this->item('promocja_10_99', 'Promocja 10-99', null, 2, 11),
        ]);

        self::assertSame('synchronized', $results[0]['status']);
        self::assertSame('remote-promotion', $results[0]['remote_id']);
        self::assertSame('skipped', $results[1]['status']);
        self::assertArrayNotHasKey('remote_id', $results[1]);
    }

    public function testSkipsRemoteCodeCollisionWithoutUpdatingOrMappingCategory(): void
    {
        $state = $this->createStub(CategoryStateInterface::class);
        $state->method('getCode')->willReturn('123');
        $stateFactory = $this->createStub(CategoryDesiredStateFactoryInterface::class);
        $stateFactory->method('createCategory')->willReturn($state);
        $synchronizer = $this->createMock(CategoryBatchSynchronizerInterface::class);
        $synchronizer->expects(self::once())->method('synchronizeBatch')->willReturn([
            '123' => new CategorySynchronizationResult(
                CategorySynchronizationResultInterface::STATUS_NOOP,
                CategorySynchronizationResultInterface::REFERENCE_PRESENT
            ),
        ]);
        $identities = $this->createMock(CategoryRemoteIdentityProviderInterface::class);
        $identities->expects(self::never())->method('getIdsByCode');
        $writer = $this->createMock(CategoryRemoteIdentityWriterInterface::class);
        $writer->expects(self::never())->method('saveRemoteIdentities');
        $gateway = $this->createMock(CategoryTreeGateway::class);
        $gateway->expects(self::never())->method('requireCategoryId');
        $collisionLogger = $this->createMock(CategoryCreationCollisionLogger::class);
        $collisionLogger->expects(self::once())->method('warning')->with(
            7,
            '123',
            10,
            'Promocja 10.99',
            'ergonode'
        );

        $results = $this->publisher(
            $stateFactory,
            $synchronizer,
            $identities,
            $writer,
            $gateway,
            $collisionLogger
        )->publish(7, [$this->item('123', 'Promocja 10.99', null, 1, 10)]);

        self::assertSame('skipped', $results[0]['status']);
        self::assertStringContainsString('already used', $results[0]['message']);
    }

    public function testReusesAllowedExistingRemoteCategoryForTreeAttachment(): void
    {
        $state = $this->createStub(CategoryStateInterface::class);
        $state->method('getCode')->willReturn('0');
        $stateFactory = $this->createStub(CategoryDesiredStateFactoryInterface::class);
        $stateFactory->method('createCategory')->willReturn($state);
        $synchronizer = $this->createMock(CategoryBatchSynchronizerInterface::class);
        $synchronizer->expects(self::once())->method('synchronizeBatch')->willReturn([
            '0' => new CategorySynchronizationResult(
                CategorySynchronizationResultInterface::STATUS_NOOP,
                CategorySynchronizationResultInterface::REFERENCE_PRESENT
            ),
        ]);
        $identities = $this->createStub(CategoryRemoteIdentityProviderInterface::class);
        $identities->method('getIdsByCode')->with(7, ['0'])->willReturn([]);
        $writer = $this->createMock(CategoryRemoteIdentityWriterInterface::class);
        $writer->expects(self::once())->method('saveRemoteIdentities')->with(7, [[
            'code' => '0',
            'remote_id' => 'remote-men',
            'manual_parent_code' => 'fashion',
            'manual_sort_order' => 4,
            'magento_category_id' => 12,
        ]]);
        $gateway = $this->createMock(CategoryTreeGateway::class);
        $gateway->expects(self::once())->method('getCategoryIds')->with(['0'])->willReturn(['0' => 'remote-men']);
        $collisionLogger = $this->createMock(CategoryCreationCollisionLogger::class);
        $collisionLogger->expects(self::never())->method('warning');

        $results = $this->publisher(
            $stateFactory,
            $synchronizer,
            $identities,
            $writer,
            $gateway,
            $collisionLogger
        )->publish(7, [$this->item('0', 'Men', 'fashion', 4, 12)], ['0']);

        self::assertSame('existing', $results[0]['status']);
        self::assertSame('remote-men', $results[0]['remote_id']);
        self::assertStringContainsString('already exists', $results[0]['message']);
    }

    public function testPropagatesRateLimitWithoutPersistingIncompleteBatch(): void
    {
        $state = $this->createStub(CategoryStateInterface::class);
        $stateFactory = $this->createStub(CategoryDesiredStateFactoryInterface::class);
        $stateFactory->method('createCategory')->willReturn($state);
        $operation = $this->createStub(MutationOperationInterface::class);
        $mutationResult = new MutationResult(
            MutationResultInterface::STATUS_TRANSIENT_FAILURE,
            'category_0',
            $operation,
            null,
            [[
                'message' => 'Too Many Requests — internal Magento limit.',
                'extensions' => [
                    'failure_type' => GraphQlRequestException::FAILURE_RATE_LIMIT,
                    'retry_after_seconds' => 30,
                ],
            ]]
        );
        $synchronizer = $this->createStub(CategoryBatchSynchronizerInterface::class);
        $synchronizer->method('synchronizeBatch')->willReturn([
            'chairs' => new CategorySynchronizationResult(
                CategorySynchronizationResultInterface::STATUS_FAILED,
                CategorySynchronizationResultInterface::REFERENCE_UNKNOWN,
                [$mutationResult],
                'Too Many Requests — internal Magento limit.'
            ),
        ]);
        $identityWriter = $this->createMock(CategoryRemoteIdentityWriterInterface::class);
        $identityWriter->expects(self::never())->method('saveRemoteIdentities');
        $publisher = $this->publisher(
            $stateFactory,
            $synchronizer,
            $this->createStub(CategoryRemoteIdentityProviderInterface::class),
            $identityWriter,
            $this->createStub(CategoryTreeGateway::class)
        );

        try {
            $publisher->publish(7, [$this->item('chairs', 'Krzesła')]);
            self::fail('A GraphQL rate-limit exception was expected.');
        } catch (GraphQlRequestException $exception) {
            self::assertSame(GraphQlRequestException::FAILURE_RATE_LIMIT, $exception->getFailureType());
            self::assertSame(30, $exception->getRetryAfterSeconds());
            self::assertSame('Too Many Requests — internal Magento limit.', $exception->getMessage());
        }
    }

    public function testIdentityOnlyBatchDoesNotRequireAdminLanguage(): void
    {
        $stateFactory = $this->createMock(CategoryDesiredStateFactoryInterface::class);
        $stateFactory->expects(self::never())->method('createCategory');
        $synchronizer = $this->createMock(CategoryBatchSynchronizerInterface::class);
        $synchronizer->expects(self::never())->method('synchronizeBatch');
        $identityProvider = $this->createStub(CategoryRemoteIdentityProviderInterface::class);
        $identityProvider->method('getIdsByCode')->willReturn(['chairs' => 'remote-chairs']);
        $identityWriter = $this->createStub(CategoryRemoteIdentityWriterInterface::class);
        $gateway = $this->createMock(CategoryTreeGateway::class);
        $gateway->expects(self::never())->method('requireCategoryId');
        $language = $this->createMock(LanguageStoreMappingProviderInterface::class);
        $language->expects(self::never())->method('requireAdminLanguageCode');
        $publisher = new CategoryBatchPublisher(
            new CategoryCreationStateBuilder($stateFactory, $language),
            $synchronizer,
            new CategoryRemoteIdentityResolver($identityProvider, $identityWriter, $gateway),
            $this->createStub(CategoryCreationCollisionLogger::class),
            $this->createStub(CategoryPublicationCheckpoint::class),
            $this->createStub(CategoryPublicationDetails::class)
        );

        $results = $publisher->publish(7, [[
            'code' => 'chairs',
            'label' => 'Krzesła',
            'sort_order' => 1,
        ]]);

        self::assertSame('verified', $results[0]['status']);
        self::assertSame('remote-chairs', $results[0]['remote_id']);
    }

    private function publisher(
        CategoryDesiredStateFactoryInterface $stateFactory,
        CategoryBatchSynchronizerInterface $synchronizer,
        CategoryRemoteIdentityProviderInterface $identityProvider,
        CategoryRemoteIdentityWriterInterface $identityWriter,
        CategoryTreeGateway $gateway,
        ?CategoryCreationCollisionLogger $collisionLogger = null
    ): CategoryBatchPublisher {
        $language = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $language->method('requireAdminLanguageCode')->willReturn('pl_PL');

        return new CategoryBatchPublisher(
            new CategoryCreationStateBuilder($stateFactory, $language),
            $synchronizer,
            new CategoryRemoteIdentityResolver($identityProvider, $identityWriter, $gateway),
            $collisionLogger ?? $this->createStub(CategoryCreationCollisionLogger::class),
            $this->createStub(CategoryPublicationCheckpoint::class),
            $this->createStub(CategoryPublicationDetails::class)
        );
    }

    /** @return array<string, mixed> */
    private function item(
        string $code,
        string $label,
        ?string $parentCode = null,
        int $sortOrder = 0,
        ?int $magentoCategoryId = null
    ): array {
        return [
            'code' => $code,
            'label' => $label,
            'parent_code' => $parentCode,
            'sort_order' => $sortOrder,
            'magento_category_id' => $magentoCategoryId,
            'extension_data' => ['to_ergonode' => ['pending_create' => true, 'label' => $label]],
        ];
    }
}
