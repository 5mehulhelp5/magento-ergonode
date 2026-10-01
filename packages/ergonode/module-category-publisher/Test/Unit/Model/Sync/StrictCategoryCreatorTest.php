<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisher\Test\Unit\Model\Sync;

use Ergonode\CategoryPublisher\Api\Data\CategorySynchronizationResultInterface as Result;
use Ergonode\CategoryPublisher\Model\Data\CategoryStateDto;
use Ergonode\CategoryPublisher\Model\GraphQl\CategoryMutationFactory;
use Ergonode\CategoryPublisher\Model\Sync\CategoryStateLoader;
use Ergonode\CategoryPublisher\Model\Sync\StrictCategoryCreator;
use Ergonode\Core\Api\Exception\GraphQlRequestException;
use Ergonode\Core\Api\GraphQlMutationClientInterface;
use Ergonode\Core\Api\GraphQlWriteScopeQueryClientInterface;
use Ergonode\Publisher\Api\RetryDelayInterface;
use Ergonode\Publisher\Model\GraphQl\MutationAliasGenerator;
use Ergonode\Publisher\Model\GraphQl\MutationBatchBuilder;
use Ergonode\Publisher\Model\GraphQl\MutationBatchPlanner;
use Ergonode\Publisher\Model\GraphQl\MutationErrorMapper;
use Ergonode\Publisher\Model\GraphQl\MutationExecutor;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class StrictCategoryCreatorTest extends TestCase
{
    public function testNumericAndLeadingZeroCodesKeepTheirIdentityWithoutConfirmationReads(): void
    {
        $read = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $read->expects(self::never())->method('queryWriteScope');
        $write = $this->createMock(GraphQlMutationClientInterface::class);
        $write->expects(self::once())->method('mutateWithResponse')->willReturnCallback(
            static function (string $document, array $variables): array {
                self::assertSame(['0', '123', '001'], array_column(array_values($variables), 'code'));
                return self::success($variables);
            }
        );
        $states = [];
        foreach (['0', '123', '001'] as $code) {
            $states[$code] = new CategoryStateDto($code, ['pl_PL' => $code]);
        }
        $results = $this->creator($read, $write)->create($states);
        self::assertSame([0, 123, '001'], array_keys($results));
        foreach ($results as $result) {
            self::assertSame(Result::STATUS_SUCCESS, $result->getStatus());
        }
    }

    public function testCreatesFiftyCategoriesWithOneMutationAndNoQueries(): void
    {
        $read = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $read->expects(self::never())->method('queryWriteScope');
        $write = $this->createMock(GraphQlMutationClientInterface::class);
        $write->expects(self::once())->method('mutateWithResponse')->willReturnCallback(
            static function (string $document, array $variables): array {
                self::assertSame(50, substr_count($document, ': categoryCreate('));
                self::assertCount(50, $variables);
                return self::success($variables);
            }
        );
        $results = $this->creator($read, $write)->create($this->states(50));

        self::assertCount(50, $results);
        foreach ($results as $result) {
            self::assertSame(Result::STATUS_SUCCESS, $result->getStatus());
            self::assertSame(Result::REFERENCE_PRESENT, $result->getReferenceStatus());
        }
    }

    public function testFiftyOneCategoriesSplitIntoTwoMutationsWithoutReads(): void
    {
        $read = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $read->expects(self::never())->method('queryWriteScope');
        $write = $this->createMock(GraphQlMutationClientInterface::class);
        $sizes = [];
        $write->expects(self::exactly(2))->method('mutateWithResponse')->willReturnCallback(
            static function (string $document, array $variables) use (&$sizes): array {
                self::assertStringContainsString('mutation PublishBatch', $document);
                $sizes[] = count($variables);
                return self::success($variables);
            }
        );
        self::assertCount(51, $this->creator($read, $write)->create($this->states(51)));
        self::assertSame([50, 1], $sizes);
    }

    public function testReadsOnlyRejectedCategoriesAndPreservesSuccessfulSibling(): void
    {
        $read = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $read->expects(self::once())->method('queryWriteScope')
            ->with(self::stringContains('PublisherCategoryExistence'), ['code_0' => 'c_2', 'code_1' => 'c_3'])
            ->willReturn(['category_0' => ['code' => 'c_2'], 'category_1' => null]);
        $write = $this->createMock(GraphQlMutationClientInterface::class);
        $write->expects(self::once())->method('mutateWithResponse')->willReturn([
            'data' => ['categoryCreate' => ['category' => ['code' => 'c_1']]],
            'errors' => [
                ['message' => 'Category already exists.', 'path' => ['categoryCreate_2']],
                ['message' => 'Invalid category name.', 'path' => ['categoryCreate_3']],
            ],
        ]);
        $results = $this->creator($read, $write)->create($this->states(3));
        self::assertSame(Result::STATUS_SUCCESS, $results['c_1']->getStatus());
        self::assertSame(Result::STATUS_NOOP, $results['c_2']->getStatus());
        self::assertSame(Result::STATUS_FAILED, $results['c_3']->getStatus());
        self::assertSame('Invalid category name.', $results['c_3']->getMessage());
    }

    public function testAmbiguousFiftyCategoryWriteIsVerifiedInOneQueryWithoutRecreating(): void
    {
        $read = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $read->expects(self::once())->method('queryWriteScope')->willReturnCallback(
            static function (string $document, array $variables): array {
                self::assertStringContainsString('PublisherCategoryExistence', $document);
                self::assertCount(50, $variables);
                $data = [];
                foreach (array_values($variables) as $index => $code) {
                    $data['category_' . $index] = ['code' => $code];
                }
                return $data;
            }
        );
        $write = $this->createMock(GraphQlMutationClientInterface::class);
        $write->expects(self::once())->method('mutateWithResponse')->willThrowException(
            new GraphQlRequestException('Response lost.', GraphQlRequestException::FAILURE_AMBIGUOUS_TRANSPORT, 502)
        );
        foreach ($this->creator($read, $write)->create($this->states(50)) as $result) {
            self::assertSame(Result::STATUS_SUCCESS, $result->getStatus());
        }
    }

    public function testAmbiguousRetryRefreshesAbsenceAndRetriesOnlyMissingCategory(): void
    {
        $read = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $read->expects(self::exactly(2))->method('queryWriteScope')->willReturnOnConsecutiveCalls(
            ['category_0' => ['code' => 'c_1'], 'category_1' => null],
            ['category_0' => ['code' => 'c_1'], 'category_1' => ['code' => 'c_2']]
        );
        $write = $this->createMock(GraphQlMutationClientInterface::class);
        $attempt = 0;
        $write->expects(self::exactly(2))->method('mutateWithResponse')->willReturnCallback(
            static function (string $document, array $variables) use (&$attempt): array {
                self::assertStringContainsString('categoryCreate', $document);
                self::assertCount(++$attempt === 1 ? 2 : 1, $variables);
                if ($attempt === 2) {
                    self::assertSame('c_2', array_values($variables)[0]['code']);
                }
                throw new GraphQlRequestException(
                    'Response lost.',
                    GraphQlRequestException::FAILURE_AMBIGUOUS_TRANSPORT,
                    502
                );
            }
        );
        foreach ($this->creator($read, $write)->create($this->states(2)) as $result) {
            self::assertTrue($result->isSuccessful());
        }
    }

    public function testRateLimitRetriesWithoutVerificationQueries(): void
    {
        $read = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $read->expects(self::never())->method('queryWriteScope');
        $write = $this->createMock(GraphQlMutationClientInterface::class);
        $attempt = 0;
        $write->expects(self::exactly(2))->method('mutateWithResponse')->willReturnCallback(
            static function (string $document, array $variables) use (&$attempt): array {
                self::assertStringContainsString('categoryCreate', $document);
                if (++$attempt === 1) {
                    throw new GraphQlRequestException(
                        'Rate limited.',
                        GraphQlRequestException::FAILURE_RATE_LIMIT,
                        429,
                        7
                    );
                }
                return self::success($variables);
            }
        );
        self::assertTrue($this->creator($read, $write)->create($this->states(1))['c_1']->isSuccessful());
    }

    public function testAuthorizationFailureStopsWithoutReadsOrFurtherMutations(): void
    {
        $read = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $read->expects(self::never())->method('queryWriteScope');
        $write = $this->createMock(GraphQlMutationClientInterface::class);
        $write->expects(self::once())->method('mutateWithResponse')->willThrowException(
            new GraphQlRequestException('Access denied.', GraphQlRequestException::FAILURE_AUTHORIZATION, 403)
        );
        $results = $this->creator($read, $write)->create($this->states(2));
        self::assertSame(Result::STATUS_FAILED, $results['c_1']->getStatus());
        self::assertSame('Access denied.', $results['c_1']->getMessage());
    }

    public function testMalformedSuccessRequiresVerificationAndCannotSilentlySucceed(): void
    {
        $read = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $read->expects(self::once())->method('queryWriteScope')->willReturn(['category_0' => null]);
        $write = $this->createMock(GraphQlMutationClientInterface::class);
        $write->expects(self::once())->method('mutateWithResponse')->willReturn([
            'data' => ['categoryCreate' => ['category' => ['code' => 'wrong']]],
        ]);
        $results = $this->creator($read, $write)->create($this->states(1));
        self::assertSame(Result::STATUS_FAILED, $results['c_1']->getStatus());
    }

    public function testMissingVerificationAliasNeverReplaysAnAmbiguousCreate(): void
    {
        $read = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $read->expects(self::once())->method('queryWriteScope')->willReturn([]);
        $write = $this->createMock(GraphQlMutationClientInterface::class);
        $write->expects(self::once())->method('mutateWithResponse')->willThrowException(
            new GraphQlRequestException('Response lost.', GraphQlRequestException::FAILURE_AMBIGUOUS_TRANSPORT, 502)
        );
        $result = $this->creator($read, $write)->create($this->states(1))['c_1'];
        self::assertSame(Result::STATUS_FAILED, $result->getStatus());
        self::assertSame(Result::REFERENCE_UNKNOWN, $result->getReferenceStatus());
    }

    private function creator(
        GraphQlWriteScopeQueryClientInterface $read,
        GraphQlMutationClientInterface $write
    ): StrictCategoryCreator {
        $builder = new MutationBatchBuilder(new MutationAliasGenerator(), new Json());
        return new StrictCategoryCreator(
            new CategoryStateLoader($read),
            new CategoryMutationFactory(),
            new MutationBatchPlanner($builder),
            new MutationExecutor(
                $write,
                $builder,
                new MutationErrorMapper(),
                $this->createStub(RetryDelayInterface::class)
            )
        );
    }

    /** @return array<string, CategoryStateDto> */
    private function states(int $count): array
    {
        $states = [];
        for ($index = 1; $index <= $count; ++$index) {
            $states['c_' . $index] = new CategoryStateDto('c_' . $index, ['pl_PL' => 'Kategoria ' . $index]);
        }
        return $states;
    }

    /** @param array<string, array<string, mixed>> $variables @return array<string, mixed> */
    private static function success(array $variables): array
    {
        $data = [];
        foreach ($variables as $name => $input) {
            $data[substr($name, 0, -6)] = ['category' => ['code' => $input['code']]];
        }
        return ['data' => $data];
    }
}
