<?php

declare(strict_types=1);

namespace Ergonode\CategoryAdminUi\Block\Adminhtml\Test\Unit\CategoryTree\Mapping;

use Ergonode\CategoryAdminUi\Controller\Adminhtml\Category\Tree\Mapping\Load;
use Ergonode\CategoryAdminUi\Model\CategoryTreeMappingUiProvider;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use PHPUnit\Framework\TestCase;

class LoadControllerTest extends TestCase
{
    public function testReturnsSelectedCategoryTreeConfigAsJson(): void
    {
        $config = [
            'current_category_tree' => ['category_tree_id' => 7],
            'status' => ['error' => null],
        ];
        $provider = $this->createMock(CategoryTreeMappingUiProvider::class);
        $provider->expects(self::once())->method('getConfig')->willReturn($config);
        $result = $this->createMock(Json::class);
        $result->expects(self::once())
            ->method('setData')
            ->with([
                'success' => true,
                'message' => null,
                'config' => $config,
            ])
            ->willReturnSelf();
        $resultFactory = $this->createMock(ResultFactory::class);
        $resultFactory->expects(self::once())
            ->method('create')
            ->with(ResultFactory::TYPE_JSON)
            ->willReturn($result);
        $context = $this->createStub(Context::class);
        $context->method('getResultFactory')->willReturn($resultFactory);

        self::assertSame($result, (new Load($context, $provider))->execute());
    }
}
