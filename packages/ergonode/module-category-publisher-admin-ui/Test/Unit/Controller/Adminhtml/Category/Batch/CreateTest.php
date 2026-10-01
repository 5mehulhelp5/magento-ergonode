<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Block\Adminhtml\Test\Unit\Category\Batch;

use Ergonode\CategoryPublisherAdminUi\Controller\Adminhtml\Category\Batch\Create;
use Ergonode\CategoryPublisherAdminUi\Model\ManualRest\RetryableRequestException;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryBatchPublisher;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class CreateTest extends TestCase
{
    public function testAllowsExistingRemoteCategoryCodesToBeAttachedToTheTree(): void
    {
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturnMap([
            ['items', '', '[{"code":"men","label":"Men"}]'],
            ['category_tree_id', 0, 7],
        ]);
        $publisher = $this->createMock(CategoryBatchPublisher::class);
        $publisher->expects(self::once())->method('publish')->with(
            7,
            [['code' => 'men', 'label' => 'Men']],
            ['men']
        )->willReturn([[
            'code' => 'men',
            'label' => 'Men',
            'status' => 'existing',
            'message' => 'Category already exists in Ergonode.',
            'remote_id' => 'remote-men',
        ]]);
        $result = $this->createMock(JsonResult::class);
        $result->expects(self::once())->method('setData')->with([
            'success' => true,
            'items' => [[
                'code' => 'men',
                'label' => 'Men',
                'status' => 'existing',
                'message' => 'Category already exists in Ergonode.',
                'remote_id' => 'remote-men',
            ]],
            'stats' => [
                'processed' => 1,
                'successful' => 1,
                'failed' => 0,
                'skipped' => 0,
            ],
        ])->willReturnSelf();
        self::assertSame($result, $this->execute($request, $publisher, $result));
    }

    public function testReturnsRetryCountdownWithoutConvertingRateLimitToGenericFailure(): void
    {
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturnMap([
            ['items', '', '[{"code":"chairs"}]'],
            ['category_tree_id', 0, 7],
        ]);
        $publisher = $this->createStub(CategoryBatchPublisher::class);
        $publisher->method('publish')->willThrowException(
            new RetryableRequestException('Wait before retrying.', 30)
        );
        $result = $this->createMock(JsonResult::class);
        $result->expects(self::once())->method('setData')->with([
            'success' => false,
            'failure_type' => 'retryable',
            'retry_after_seconds' => 30,
            'message' => 'Wait before retrying.',
        ])->willReturnSelf();
        self::assertSame($result, $this->execute($request, $publisher, $result));
    }

    private function execute(Http $request, CategoryBatchPublisher $publisher, JsonResult $result): JsonResult
    {
        $resultFactory = $this->createMock(ResultFactory::class);
        $resultFactory->method('create')->with(ResultFactory::TYPE_JSON)->willReturn($result);
        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getResultFactory')->willReturn($resultFactory);

        return (new Create($context, new Json(), $publisher))->execute();
    }
}
