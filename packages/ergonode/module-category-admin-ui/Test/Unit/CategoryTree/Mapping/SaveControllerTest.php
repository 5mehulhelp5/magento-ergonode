<?php

declare(strict_types=1);

namespace Ergonode\CategoryAdminUi\Block\Adminhtml\Test\Unit\CategoryTree\Mapping;

use Ergonode\Category\Api\CategoryLayoutSaverInterface;
use Ergonode\CategoryAdminUi\Controller\Adminhtml\Category\Tree\Mapping\Save;
use Ergonode\CoreAdminUi\Model\Request\MappingPayloadDecoder;
use Ergonode\Core\Api\Exception\GraphQlRequestException;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class SaveControllerTest extends TestCase
{
    public function testReturnsRetryCountdownWhenFinalTreeUpdateIsRateLimited(): void
    {
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturnMap([
            ['payload', '', '{"category_tree_id":7,"categories":[]}'],
        ]);
        $saver = $this->createStub(CategoryLayoutSaverInterface::class);
        $saver->method('save')->willThrowException(new GraphQlRequestException(
            'Wait before retrying.',
            GraphQlRequestException::FAILURE_RATE_LIMIT,
            429,
            30
        ));
        $result = $this->createMock(JsonResult::class);
        $result->expects(self::once())->method('setData')->with([
            'success' => false,
            'failure_type' => 'retryable',
            'retry_after_seconds' => 30,
            'message' => 'Wait before retrying.',
        ])->willReturnSelf();
        $resultFactory = $this->createMock(ResultFactory::class);
        $resultFactory->method('create')->with(ResultFactory::TYPE_JSON)->willReturn($result);
        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getResultFactory')->willReturn($resultFactory);

        self::assertSame(
            $result,
            (new Save($context, new MappingPayloadDecoder(new Json()), $saver))->execute()
        );
    }

    public function testStopsFinalTreeUpdateOnAuthorizationFailure(): void
    {
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturnMap([
            ['payload', '', '{"category_tree_id":7,"categories":[]}'],
        ]);
        $saver = $this->createStub(CategoryLayoutSaverInterface::class);
        $saver->method('save')->willThrowException(new GraphQlRequestException(
            'Ergonode updates are disabled.',
            GraphQlRequestException::FAILURE_AUTHORIZATION,
            403,
            30
        ));
        $result = $this->createMock(JsonResult::class);
        $result->expects(self::once())->method('setData')->with([
            'success' => false,
            'failure_type' => 'authorization',
            'message' => 'Ergonode updates are disabled.',
        ])->willReturnSelf();
        $resultFactory = $this->createMock(ResultFactory::class);
        $resultFactory->method('create')->with(ResultFactory::TYPE_JSON)->willReturn($result);
        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getResultFactory')->willReturn($resultFactory);

        self::assertSame(
            $result,
            (new Save($context, new MappingPayloadDecoder(new Json()), $saver))->execute()
        );
    }

    public function testExpiredManualSessionRequestsLoginWithoutAutomaticRetry(): void
    {
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturnMap([
            ['payload', '', '{"category_tree_id":7,"categories":[]}'],
        ]);
        $saver = $this->createMock(CategoryLayoutSaverInterface::class);
        $saver->expects(self::once())->method('save')->willThrowException(
            new AuthenticationException(__('Your Ergonode session has expired. Log in again.'))
        );
        $result = $this->createMock(JsonResult::class);
        $result->expects(self::once())->method('setData')->with([
            'success' => false,
            'failure_type' => 'authentication_required',
            'message' => 'Your Ergonode session has expired. Log in again.',
        ])->willReturnSelf();
        $factory = $this->createStub(ResultFactory::class);
        $factory->method('create')->willReturn($result);
        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getResultFactory')->willReturn($factory);

        self::assertSame($result, (new Save($context, new MappingPayloadDecoder(new Json()), $saver))->execute());
    }

    public function testReturnsSaveStatisticsWithoutReplacingUiModels(): void
    {
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturnMap([
            ['payload', '', '{"category_tree_id":7,"categories":[],"visibility":[]}'],
        ]);
        $saver = $this->createMock(CategoryLayoutSaverInterface::class);
        $saver->expects(self::once())->method('save')->with(7, [], [])->willReturn(['saved' => 1]);
        $result = $this->createMock(JsonResult::class);
        $result->expects(self::once())->method('setData')->with([
            'success' => true,
            'message' => 'Category layout has been saved.',
            'category_tree_id' => 7,
            'stats' => ['saved' => 1],
        ])->willReturnSelf();
        $resultFactory = $this->createStub(ResultFactory::class);
        $resultFactory->method('create')->willReturn($result);
        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getResultFactory')->willReturn($resultFactory);

        self::assertSame(
            $result,
            (new Save($context, new MappingPayloadDecoder(new Json()), $saver))->execute()
        );
    }
}
