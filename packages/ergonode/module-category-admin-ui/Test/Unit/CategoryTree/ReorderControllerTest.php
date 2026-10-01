<?php

declare(strict_types=1);

namespace Ergonode\CategoryAdminUi\Block\Adminhtml\Test\Unit\CategoryTree;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use PHPUnit\Framework\TestCase;
use Ergonode\Category\Model\CategoryTree\CategoryTreeRepository;
use Ergonode\CategoryAdminUi\Controller\Adminhtml\Category\Tree\Reorder;

class ReorderControllerTest extends TestCase
{
    public function testPersistsSubmittedOrderAndReturnsJsonSuccess(): void
    {
        $request = $this->createMock(Http::class);
        $request->expects(self::once())
            ->method('getParam')
            ->with('category_tree_ids', [])
            ->willReturn(['7', '4']);
        $result = $this->createMock(Json::class);
        $result->expects(self::once())
            ->method('setData')
            ->with(self::callback(
                static fn (array $data): bool => $data['success'] === true
                    && $data['message'] === 'Category Tree order has been saved.'
            ))
            ->willReturnSelf();
        $resultFactory = $this->createMock(ResultFactory::class);
        $resultFactory->expects(self::once())
            ->method('create')
            ->with(ResultFactory::TYPE_JSON)
            ->willReturn($result);
        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getResultFactory')->willReturn($resultFactory);
        $repository = $this->createMock(CategoryTreeRepository::class);
        $repository->expects(self::once())->method('reorder')->with([7, 4]);

        $controller = new Reorder($context, $repository);

        self::assertSame($result, $controller->execute());
    }
}
