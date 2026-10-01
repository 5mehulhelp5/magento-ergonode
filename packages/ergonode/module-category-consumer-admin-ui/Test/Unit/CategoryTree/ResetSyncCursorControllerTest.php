<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerAdminUi\Block\Adminhtml\Test\Unit\CategoryTree;

use Magento\Framework\App\RequestInterface;

use Ergonode\CategoryConsumer\Api\CategorySynchronizationActionInterface;
use Ergonode\CategoryConsumerAdminUi\Controller\Adminhtml\Category\Tree\ResetSyncCursor;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ResetSyncCursorControllerTest extends TestCase
{
    public function testResetsCursor(): void
    {
        $cursorResetter = $this->createMock(CategorySynchronizationActionInterface::class);
        $cursorResetter->expects(self::once())->method('execute')->with('data', 'reset-cursor');
        $json = $this->createMock(Json::class);
        $json->expects(self::once())
            ->method('setData')
            ->with(self::callback(static fn (array $data): bool => $data['success'] === true
                && !array_key_exists('sync_metadata', $data)))
            ->willReturnSelf();
        $resultFactory = $this->createMock(ResultFactory::class);
        $resultFactory->expects(self::once())
            ->method('create')
            ->with(ResultFactory::TYPE_JSON)
            ->willReturn($json);
        $context = $this->createStub(Context::class);
        $context->method('getResultFactory')->willReturn($resultFactory);
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturn('data');
        $context->method('getRequest')->willReturn($request);

        $controller = new ResetSyncCursor(
            $context,
            $cursorResetter,
            new NullLogger()
        );

        self::assertSame($json, $controller->execute());
    }
}
