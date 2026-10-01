<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Test\Unit\Model\GraphQl;

use Ergonode\Core\Api\Exception\GraphQlRequestException;
use Ergonode\Core\Api\GraphQlMutationClientInterface;
use Ergonode\Publisher\Api\Exception\MutationVerificationException;
use Magento\Framework\Phrase;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use TypeError;
use Ergonode\Publisher\Api\AmbiguousMutationVerifierInterface;
use Ergonode\Publisher\Api\MutationVerificationRoundInterface;
use Ergonode\Publisher\Api\Data\MutationResultInterface;
use Ergonode\Publisher\Api\RetryDelayInterface;
use Ergonode\Publisher\Model\Data\MutationOperation;
use Ergonode\Publisher\Model\Data\MutationVariable;
use Ergonode\Publisher\Model\Data\MutationVerificationResult;
use Ergonode\Publisher\Model\GraphQl\MutationAliasGenerator;
use Ergonode\Publisher\Model\GraphQl\MutationBatchBuilder;
use Ergonode\Publisher\Model\GraphQl\MutationErrorMapper;
use Ergonode\Publisher\Model\GraphQl\MutationExecutor;

class MutationExecutorTest extends TestCase
{
    public function testStartsNewVerificationRoundBeforeEveryMutationAttempt(): void
    {
        $round = 0;
        $verifier = $this->createMock(MutationVerificationRoundInterface::class);
        $verifier->expects(self::exactly(2))->method('beginVerificationRound')->willReturnCallback(
            static function () use (&$round): void {
                ++$round;
            }
        );
        $verifier->expects(self::once())->method('verify')->willReturnCallback(
            static function () use (&$round): MutationVerificationResult {
                self::assertSame(1, $round);
                return new MutationVerificationResult('not_applied');
            }
        );
        $client = $this->createMock(GraphQlMutationClientInterface::class);
        $client->expects(self::exactly(2))->method('mutateWithResponse')->willReturnCallback(
            static function () use (&$round): array {
                if ($round === 1) {
                    throw new GraphQlRequestException(
                        'lost response',
                        GraphQlRequestException::FAILURE_AMBIGUOUS_TRANSPORT,
                        504
                    );
                }
                self::assertSame(2, $round);
                return ['data' => ['publish' => ['id' => 'done']]];
            }
        );
        self::assertTrue($this->executor($client)->execute(
            $this->builder()->build([$this->operation('publish')]),
            $verifier
        )->isSuccessful());
    }

    public function testCorrelatesSuccessValidationFailureAndMissingResultKey(): void
    {
        $client = $this->createMock(GraphQlMutationClientInterface::class);
        $client->expects(self::once())->method('mutateWithResponse')->willReturn([
            'data' => [
                'first' => ['id' => 'one'],
                'second' => null,
            ],
            'errors' => [[
                'message' => 'Code is invalid.',
                'path' => ['second'],
                'extensions' => ['code' => 'BAD_USER_INPUT'],
            ]],
        ]);
        $batch = $this->builder()->build([
            $this->operation('first'),
            $this->operation('second'),
            $this->operation('third'),
        ]);

        $result = $this->executor($client)->execute($batch);

        self::assertSame([
            MutationResultInterface::STATUS_SUCCESS,
            MutationResultInterface::STATUS_VALIDATION_FAILURE,
            MutationResultInterface::STATUS_UNRESOLVED,
        ], array_map(static fn (MutationResultInterface $item): string => $item->getStatus(), $result->getResults()));
        self::assertSame(['id' => 'one'], $result->getResults()[0]->getData());
        self::assertSame('Code is invalid.', $result->getResults()[1]->getErrors()[0]['message']);
        self::assertFalse($result->isSuccessful());
    }

    public function testRetriesRateLimitUsingRetryAfterWithoutVerification(): void
    {
        $client = $this->createMock(GraphQlMutationClientInterface::class);
        $attempt = 0;
        $client->expects(self::exactly(2))
            ->method('mutateWithResponse')
            ->willReturnCallback(static function () use (&$attempt): array {
                if (++$attempt === 1) {
                    throw new GraphQlRequestException(
                        'Rate limited.',
                        GraphQlRequestException::FAILURE_RATE_LIMIT,
                        429,
                        7
                    );
                }

                return ['data' => ['publish' => ['id' => 'done']]];
            });
        $delay = $this->createMock(RetryDelayInterface::class);
        $delay->expects(self::once())->method('wait')->with(7);

        $result = $this->executor($client, $delay)->execute(
            $this->builder()->build([$this->operation('publish')])
        );

        self::assertTrue($result->isSuccessful());
        self::assertSame(2, $result->getResults()[0]->getAttempts());
    }

    public function testExhaustedRateLimitKeepsStructuredRetryMetadata(): void
    {
        $client = $this->createMock(GraphQlMutationClientInterface::class);
        $client->expects(self::once())->method('mutateWithResponse')->willThrowException(
            new GraphQlRequestException(
                'Rate limited.',
                GraphQlRequestException::FAILURE_RATE_LIMIT,
                429,
                19
            )
        );
        $executor = new MutationExecutor(
            $client,
            $this->builder(),
            new MutationErrorMapper(),
            $this->createStub(RetryDelayInterface::class),
            1,
            0
        );

        $result = $executor->execute($this->builder()->build([$this->operation('publish')]));
        $extensions = $result->getResults()[0]->getErrors()[0]['extensions'];

        self::assertSame(MutationResultInterface::STATUS_TRANSIENT_FAILURE, $result->getResults()[0]->getStatus());
        self::assertSame(GraphQlRequestException::FAILURE_RATE_LIMIT, $extensions['failure_type']);
        self::assertSame(429, $extensions['http_status']);
        self::assertSame(19, $extensions['retry_after_seconds']);
    }

    public function testVerifiesAmbiguousFailureBeforeRetryingMutation(): void
    {
        $client = $this->createMock(GraphQlMutationClientInterface::class);
        $attempt = 0;
        $client->expects(self::exactly(2))
            ->method('mutateWithResponse')
            ->willReturnCallback(static function () use (&$attempt): array {
                if (++$attempt === 1) {
                    throw new GraphQlRequestException(
                        'Gateway timeout.',
                        GraphQlRequestException::FAILURE_AMBIGUOUS_TRANSPORT,
                        504
                    );
                }

                return ['data' => ['publish' => ['id' => 'done']]];
            });
        $verifier = $this->createMock(AmbiguousMutationVerifierInterface::class);
        $verifier->expects(self::once())->method('verify')->willReturn(
            new MutationVerificationResult(MutationVerificationResult::STATUS_NOT_APPLIED)
        );

        $result = $this->executor($client)->execute(
            $this->builder()->build([$this->operation('publish')]),
            $verifier
        );

        self::assertTrue($result->isSuccessful());
        self::assertSame(2, $result->getResults()[0]->getAttempts());
    }

    public function testRetriesOnlyUnresolvedOperationAfterPartialSuccess(): void
    {
        $client = $this->createMock(GraphQlMutationClientInterface::class);
        $attempt = 0;
        $client->expects(self::exactly(2))
            ->method('mutateWithResponse')
            ->willReturnCallback(static function (string $document, array $variables) use (&$attempt): array {
                if (++$attempt === 1) {
                    self::assertCount(2, $variables);

                    return ['data' => ['first' => ['id' => 'one']]];
                }

                self::assertStringNotContainsString('first:', $document);
                self::assertSame(['second_input' => ['code' => 'second']], $variables);

                return ['data' => ['second' => ['id' => 'two']]];
            });
        $verifier = $this->createMock(AmbiguousMutationVerifierInterface::class);
        $verifier->expects(self::once())
            ->method('verify')
            ->with(self::callback(
                static fn (MutationOperation $operation): bool => $operation->getMetadata()['source'] === 'second'
            ))
            ->willReturn(new MutationVerificationResult(MutationVerificationResult::STATUS_NOT_APPLIED));

        $result = $this->executor($client)->execute(
            $this->builder()->build([$this->operation('first'), $this->operation('second')]),
            $verifier
        );

        self::assertTrue($result->isSuccessful());
        self::assertSame(1, $result->getResults()[0]->getAttempts());
        self::assertSame(2, $result->getResults()[1]->getAttempts());
    }

    public function testVerificationCanResolveCommittedMutationWithoutRetry(): void
    {
        $client = $this->createMock(GraphQlMutationClientInterface::class);
        $client->expects(self::once())->method('mutateWithResponse')->willThrowException(
            new GraphQlRequestException(
                'Connection timed out.',
                GraphQlRequestException::FAILURE_AMBIGUOUS_TRANSPORT
            )
        );
        $verifier = $this->createMock(AmbiguousMutationVerifierInterface::class);
        $verifier->expects(self::once())->method('verify')->willReturn(
            new MutationVerificationResult(
                MutationVerificationResult::STATUS_APPLIED,
                ['id' => 'already-committed']
            )
        );

        $result = $this->executor($client)->execute(
            $this->builder()->build([$this->operation('publish')]),
            $verifier
        );

        self::assertTrue($result->isSuccessful());
        self::assertSame(['id' => 'already-committed'], $result->getResults()[0]->getData());
    }

    public function testReportsTransientFailureAfterRateLimitRetriesAreExhausted(): void
    {
        $client = $this->createMock(GraphQlMutationClientInterface::class);
        $client->expects(self::exactly(3))->method('mutateWithResponse')->willThrowException(
            new GraphQlRequestException(
                'Service unavailable.',
                GraphQlRequestException::FAILURE_AMBIGUOUS_TRANSPORT,
                503
            )
        );
        $verifier = $this->createMock(AmbiguousMutationVerifierInterface::class);
        $verifier->expects(self::exactly(3))->method('verify')->willReturn(
            new MutationVerificationResult(MutationVerificationResult::STATUS_NOT_APPLIED)
        );

        $result = $this->executor($client)->execute(
            $this->builder()->build([$this->operation('publish')]),
            $verifier
        );

        self::assertSame(MutationResultInterface::STATUS_TRANSIENT_FAILURE, $result->getResults()[0]->getStatus());
        self::assertSame(3, $result->getResults()[0]->getAttempts());
    }

    public function testEmptyBatchIsSuccessfulNoOp(): void
    {
        $client = $this->createMock(GraphQlMutationClientInterface::class);
        $client->expects(self::never())->method('mutateWithResponse');

        $result = $this->executor($client)->execute($this->builder()->build([]));

        self::assertTrue($result->isSuccessful());
        self::assertSame([], $result->getResults());
    }

    public function testRetriesTransientGraphQlErrorAfterNotAppliedVerification(): void
    {
        $client = $this->createMock(GraphQlMutationClientInterface::class);
        $client->expects(self::exactly(2))->method('mutateWithResponse')->willReturnOnConsecutiveCalls(
            [
                'data' => ['publish' => null],
                'errors' => [[
                    'message' => 'Service temporarily unavailable.',
                    'path' => ['publish'],
                    'extensions' => ['code' => 'SERVICE_UNAVAILABLE'],
                ]],
            ],
            ['data' => ['publish' => ['id' => 'done']]]
        );
        $verifier = $this->createMock(AmbiguousMutationVerifierInterface::class);
        $verifier->expects(self::once())->method('verify')->willReturn(
            new MutationVerificationResult(MutationVerificationResult::STATUS_NOT_APPLIED)
        );

        $result = $this->executor($client)->execute(
            $this->builder()->build([$this->operation('publish')]),
            $verifier
        );

        self::assertTrue($result->isSuccessful());
        self::assertSame(2, $result->getResults()[0]->getAttempts());
    }

    public function testDoesNotRetryTransientGraphQlErrorWithoutReadBackVerifier(): void
    {
        foreach (['TIMEOUT', 'SERVICE_UNAVAILABLE', 'TOO_MANY_REQUESTS'] as $code) {
            $client = $this->createMock(GraphQlMutationClientInterface::class);
            $client->expects(self::once())->method('mutateWithResponse')->willReturn([
                'data' => ['publish' => null],
                'errors' => [[
                    'message' => 'The mutation outcome is unknown.',
                    'path' => ['publish'],
                    'extensions' => ['code' => $code],
                ]],
            ]);

            $result = $this->executor($client)->execute(
                $this->builder()->build([$this->operation('publish')])
            );

            self::assertSame(MutationResultInterface::STATUS_TRANSIENT_FAILURE, $result->getResults()[0]->getStatus());
            self::assertSame(1, $result->getResults()[0]->getAttempts());
        }
    }

    public function testResolvesTransientGraphQlErrorWhenReadBackFindsAppliedState(): void
    {
        $client = $this->createMock(GraphQlMutationClientInterface::class);
        $client->expects(self::once())->method('mutateWithResponse')->willReturn([
            'data' => ['publish' => null],
            'errors' => [[
                'message' => 'Service temporarily unavailable.',
                'path' => ['publish'],
                'extensions' => ['code' => 'SERVICE_UNAVAILABLE'],
            ]],
        ]);
        $verifier = $this->createMock(AmbiguousMutationVerifierInterface::class);
        $verifier->expects(self::once())->method('verify')->willReturn(
            new MutationVerificationResult(MutationVerificationResult::STATUS_APPLIED, ['id' => 'existing'])
        );

        $result = $this->executor($client)->execute(
            $this->builder()->build([$this->operation('publish')]),
            $verifier
        );

        self::assertSame(MutationResultInterface::STATUS_SUCCESS, $result->getResults()[0]->getStatus());
        self::assertSame(['id' => 'existing'], $result->getResults()[0]->getData());
        self::assertSame(1, $result->getResults()[0]->getAttempts());
    }

    public function testDoesNotRetryTransientGraphQlErrorWhenReadBackIsUnknown(): void
    {
        $client = $this->createMock(GraphQlMutationClientInterface::class);
        $client->expects(self::once())->method('mutateWithResponse')->willReturn([
            'data' => ['publish' => null],
            'errors' => [[
                'message' => 'Mutation timed out.',
                'path' => ['publish'],
                'extensions' => ['code' => 'TIMEOUT'],
            ]],
        ]);
        $verifier = $this->createMock(AmbiguousMutationVerifierInterface::class);
        $verifier->expects(self::once())->method('verify')->willReturn(
            new MutationVerificationResult(MutationVerificationResult::STATUS_UNKNOWN)
        );

        $result = $this->executor($client)->execute(
            $this->builder()->build([$this->operation('publish')]),
            $verifier
        );

        self::assertSame(MutationResultInterface::STATUS_TRANSIENT_FAILURE, $result->getResults()[0]->getStatus());
        self::assertSame(1, $result->getResults()[0]->getAttempts());
    }

    public function testValidationErrorPreventsRetryWhenMixedWithTransientError(): void
    {
        $client = $this->createMock(GraphQlMutationClientInterface::class);
        $client->expects(self::once())->method('mutateWithResponse')->willReturn([
            'data' => ['publish' => null],
            'errors' => [
                [
                    'message' => 'Service temporarily unavailable.',
                    'path' => ['publish'],
                    'extensions' => ['code' => 'SERVICE_UNAVAILABLE'],
                ],
                [
                    'message' => 'Code is invalid.',
                    'path' => ['publish'],
                    'extensions' => ['code' => 'BAD_USER_INPUT'],
                ],
            ],
        ]);

        $result = $this->executor($client)->execute(
            $this->builder()->build([$this->operation('publish')])
        );

        self::assertSame(MutationResultInterface::STATUS_VALIDATION_FAILURE, $result->getResults()[0]->getStatus());
        self::assertSame(1, $result->getResults()[0]->getAttempts());
    }

    public function testAuthorizationFailureIsPermanentAndNeverRetried(): void
    {
        foreach ([401, 403] as $status) {
            $client = $this->createMock(GraphQlMutationClientInterface::class);
            $client->expects(self::once())->method('mutateWithResponse')->willThrowException(
                new GraphQlRequestException(
                    'Authorization failed.',
                    GraphQlRequestException::FAILURE_AUTHORIZATION,
                    $status
                )
            );

            $result = $this->executor($client)->execute(
                $this->builder()->build([$this->operation('publish')])
            );

            self::assertSame(
                MutationResultInterface::STATUS_PERMANENT_FAILURE,
                $result->getResults()[0]->getStatus()
            );
            self::assertSame(1, $result->getResults()[0]->getAttempts());
        }
    }

    public function testRequestConstructionFailureIsNeverRetried(): void
    {
        $client = $this->createMock(GraphQlMutationClientInterface::class);
        $client->expects(self::once())->method('mutateWithResponse')->willThrowException(
            new GraphQlRequestException(
                'Variables cannot be serialized.',
                GraphQlRequestException::FAILURE_REQUEST_CONSTRUCTION
            )
        );

        $result = $this->executor($client)->execute(
            $this->builder()->build([$this->operation('publish')])
        );

        self::assertSame(MutationResultInterface::STATUS_PERMANENT_FAILURE, $result->getResults()[0]->getStatus());
        self::assertSame(1, $result->getResults()[0]->getAttempts());
    }

    public function testUnknownVerificationKeepsAmbiguousFailureUnresolved(): void
    {
        $client = $this->createMock(GraphQlMutationClientInterface::class);
        $client->expects(self::once())->method('mutateWithResponse')->willThrowException(
            new GraphQlRequestException(
                'Gateway timeout.',
                GraphQlRequestException::FAILURE_AMBIGUOUS_TRANSPORT,
                504
            )
        );
        $verifier = $this->createMock(AmbiguousMutationVerifierInterface::class);
        $verifier->expects(self::once())->method('verify')->willReturn(
            new MutationVerificationResult(MutationVerificationResult::STATUS_UNKNOWN)
        );

        $result = $this->executor($client)->execute(
            $this->builder()->build([$this->operation('publish')]),
            $verifier
        );

        self::assertSame(MutationResultInterface::STATUS_TRANSIENT_FAILURE, $result->getResults()[0]->getStatus());
        self::assertSame(1, $result->getResults()[0]->getAttempts());
    }

    public function testDeclaredVerificationFailureIsReportedWithoutHidingIt(): void
    {
        $client = $this->createMock(GraphQlMutationClientInterface::class);
        $client->expects(self::once())->method('mutateWithResponse')->willThrowException(
            new GraphQlRequestException(
                'Gateway timeout.',
                GraphQlRequestException::FAILURE_AMBIGUOUS_TRANSPORT,
                504
            )
        );
        $verifier = $this->createMock(AmbiguousMutationVerifierInterface::class);
        $verifier->expects(self::once())->method('verify')->willThrowException(
            new MutationVerificationException(new Phrase('Read-back failed.'))
        );

        $result = $this->executor($client)->execute(
            $this->builder()->build([$this->operation('publish')]),
            $verifier
        );

        self::assertSame(MutationResultInterface::STATUS_TRANSIENT_FAILURE, $result->getResults()[0]->getStatus());
        self::assertSame('Verification failed: Read-back failed.', $result->getResults()[0]->getErrors()[1]['message']);
    }

    public function testProgrammingErrorFromClientIsNotConvertedToBusinessResult(): void
    {
        $client = $this->createMock(GraphQlMutationClientInterface::class);
        $client->expects(self::once())->method('mutateWithResponse')->willThrowException(
            new TypeError('Programming defect.')
        );

        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('Programming defect.');

        $this->executor($client)->execute(
            $this->builder()->build([$this->operation('publish')])
        );
    }

    public function testProgrammingErrorFromVerifierIsNotConvertedToBusinessResult(): void
    {
        $client = $this->createMock(GraphQlMutationClientInterface::class);
        $client->expects(self::once())->method('mutateWithResponse')->willThrowException(
            new GraphQlRequestException(
                'Gateway timeout.',
                GraphQlRequestException::FAILURE_AMBIGUOUS_TRANSPORT,
                504
            )
        );
        $verifier = $this->createMock(AmbiguousMutationVerifierInterface::class);
        $verifier->expects(self::once())->method('verify')->willThrowException(
            new TypeError('Verifier defect.')
        );

        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('Verifier defect.');

        $this->executor($client)->execute(
            $this->builder()->build([$this->operation('publish')]),
            $verifier
        );
    }

    private function operation(string $alias): MutationOperation
    {
        return new MutationOperation(
            'publish',
            ['input' => new MutationVariable('PublishInput!', ['code' => $alias])],
            ['id'],
            ['source' => $alias],
            $alias
        );
    }

    private function builder(): MutationBatchBuilder
    {
        return new MutationBatchBuilder(new MutationAliasGenerator(), new Json());
    }

    private function executor(
        GraphQlMutationClientInterface $client,
        ?RetryDelayInterface $delay = null
    ): MutationExecutor {
        $delay ??= $this->createStub(RetryDelayInterface::class);

        return new MutationExecutor(
            $client,
            $this->builder(),
            new MutationErrorMapper(),
            $delay,
            3,
            1
        );
    }
}
