<?php

declare(strict_types=1);

namespace Ergonode\PublisherAdminUi\Block\Adminhtml\Test\Unit\Controller;

use Closure;
use Ergonode\Core\Api\Exception\GraphQlRequestException;
use Ergonode\PublisherAdminUi\Controller\Adminhtml\AbstractBatchCreate;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AbstractBatchCreateTest extends TestCase
{
    public function testPublishesOnlyDecodedArrayItemsAndReturnsSharedStats(): void
    {
        $publisher = function (array $items): array {
            self::assertSame([['status' => 'created'], ['status' => 'failed']], $items);

            return $items;
        };
        [$controller, $result] = $this->createController(
            '[{"status":"created"},false,{"status":"failed"}]',
            $publisher,
            [
                'success' => true,
                'items' => [['status' => 'created'], ['status' => 'failed']],
                'stats' => ['processed' => 2, 'successful' => 1, 'failed' => 1],
            ]
        );

        self::assertSame($result, $controller->execute());
    }

    public function testReturnsAReadableValidationFailureForNonArrayPayload(): void
    {
        [$controller, $result] = $this->createController(
            'true',
            static fn (array $items): array => $items,
            ['success' => false, 'message' => 'Invalid test entity batch payload.']
        );

        self::assertSame($result, $controller->execute());
    }

    public function testReturnsRetryMetadataFromTheSharedFailureContract(): void
    {
        $publisher = static function (): array {
            throw new GraphQlRequestException(
                'Wait before retrying.',
                GraphQlRequestException::FAILURE_RATE_LIMIT,
                429,
                30
            );
        };
        [$controller, $result] = $this->createController(
            '[]',
            $publisher,
            [
                'success' => false,
                'failure_type' => 'retryable',
                'retry_after_seconds' => 30,
                'message' => 'Wait before retrying.',
            ]
        );

        self::assertSame($result, $controller->execute());
    }

    public function testDoesNotRetryPermanentOrAmbiguousGraphQlFailures(): void
    {
        foreach (['authorization', 'request_construction', 'permanent_transport', 'ambiguous_transport'] as $type) {
            $publisher = static function () use ($type): array {
                throw new GraphQlRequestException('Publication stopped.', $type, 403, 30);
            };
            [$controller, $result] = $this->createController('[]', $publisher, [
                'success' => false,
                'failure_type' => $type,
                'message' => 'Publication stopped.',
            ]);
            self::assertSame($result, $controller->execute());
        }
    }

    public function testHidesUnexpectedFailureDetails(): void
    {
        $publisher = static function (): array {
            throw new RuntimeException('Sensitive implementation detail.');
        };
        [$controller, $result] = $this->createController(
            '[]',
            $publisher,
            ['success' => false, 'message' => 'Unable to process the Ergonode test entity batch.']
        );

        self::assertSame($result, $controller->execute());
    }

    /**
     * @param array<string, mixed> $expectedData
     * @return array{AbstractBatchCreate, JsonResult}
     */
    private function createController(string $payload, Closure $publisher, array $expectedData): array
    {
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturnMap([['items', '', $payload]]);
        $result = $this->createMock(JsonResult::class);
        $result->expects(self::once())->method('setData')->with($expectedData)->willReturnSelf();
        $resultFactory = $this->createMock(ResultFactory::class);
        $resultFactory->method('create')->with(ResultFactory::TYPE_JSON)->willReturn($result);
        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getResultFactory')->willReturn($resultFactory);

        $controller = new class ($context, new Json(), $publisher) extends AbstractBatchCreate {
            public function __construct(
                Context $context,
                Json $json,
                private readonly Closure $publisher
            ) {
                parent::__construct($context, $json);
            }

            protected function publishBatch(array $items): array
            {
                return ($this->publisher)($items);
            }

            protected function getBatchEntityName(): string
            {
                return 'test entity';
            }
        };

        return [$controller, $result];
    }
}
