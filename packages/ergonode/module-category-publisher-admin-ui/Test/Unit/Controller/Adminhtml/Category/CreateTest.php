<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Block\Adminhtml\Test\Unit\Controller\Category;

use Ergonode\CategoryPublisherAdminUi\Controller\Adminhtml\Category\Create;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryFormPublisher;
use Ergonode\Core\Api\Exception\GraphQlRequestException;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use PHPUnit\Framework\TestCase;

class CreateTest extends TestCase
{
    public function testAuthorizationFailureStopsSingleCategoryPublication(): void
    {
        $publisher = $this->createMock(CategoryFormPublisher::class);
        $publisher->expects(self::once())->method('publish')->with(12)->willThrowException(
            new GraphQlRequestException('Write key rejected.', GraphQlRequestException::FAILURE_AUTHORIZATION, 403, 30)
        );
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturn(12);
        $result = $this->createMock(Json::class);
        $result->expects(self::once())->method('setData')->with([
            'success' => false,
            'failure_type' => 'authorization',
            'message' => 'Write key rejected.',
        ])->willReturnSelf();
        $factory = $this->createStub(ResultFactory::class);
        $factory->method('create')->willReturn($result);
        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getResultFactory')->willReturn($factory);

        self::assertSame($result, (new Create($context, $publisher))->execute());
    }
}
