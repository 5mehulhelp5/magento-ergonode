<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Block\Adminhtml\Test\Unit\Category\Collision;

use Ergonode\CategoryPublisherAdminUi\Controller\Adminhtml\Category\Collision\Report;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryCreationCollisionLogger;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Controller\ResultFactory;
use PHPUnit\Framework\TestCase;

class ReportTest extends TestCase
{
    public function testLogsValidatedLocalCollisionContext(): void
    {
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturnMap([
            ['category_tree_id', 0, 7],
            ['code', '', 'promocja_10_99'],
            ['skipped_magento_category_id', 0, 12],
            ['skipped_label', '', 'Promocja 10-99'],
            ['winning_magento_category_id', 0, 11],
            ['winning_label', '', 'Promocja 10.99'],
        ]);
        $collisionLogger = $this->createMock(CategoryCreationCollisionLogger::class);
        $collisionLogger->expects(self::once())->method('warning')->with(
            7,
            'promocja_10_99',
            12,
            'Promocja 10-99',
            'local_tree',
            11,
            'Promocja 10.99'
        );
        $result = $this->createMock(JsonResult::class);
        $result->expects(self::once())->method('setData')->with(['success' => true])->willReturnSelf();
        $resultFactory = $this->createStub(ResultFactory::class);
        $resultFactory->method('create')->willReturn($result);
        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getResultFactory')->willReturn($resultFactory);

        self::assertSame($result, (new Report($context, $collisionLogger))->execute());
    }
}
