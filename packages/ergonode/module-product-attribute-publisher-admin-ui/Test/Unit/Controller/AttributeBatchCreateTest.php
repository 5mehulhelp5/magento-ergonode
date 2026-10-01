<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisherAdminUi\Test\Unit\Controller;

use Ergonode\AttributePublisher\Api\Data\AttributeStateInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeSynchronizationResultInterface;
use Ergonode\ProductAttributePublisher\Api\AttributeDefinitionPublisherInterface;
use Ergonode\ProductAttributePublisherAdminUi\Controller\Adminhtml\Attribute\Batch\Create;
use Ergonode\ProductAttributePublisherAdminUi\Model\Batch\AttributeBatchPublisher;
use Ergonode\ProductAttributePublisherAdminUi\Model\ErgonodeAttributeCreator;
use Ergonode\Publisher\Model\GraphQl\SynchronizationRateLimitGuard;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class AttributeBatchCreateTest extends TestCase
{
    public function testDuplicateItemsKeepPerItemResultsAndBatchStats(): void
    {
        $sizeState = $this->createStub(AttributeStateInterface::class);
        $sizeState->method('getCode')->willReturn('size');
        $creator = $this->createStub(ErgonodeAttributeCreator::class);
        $creator->method('prepareMapping')->willReturn(['code' => 'size', 'pending_create' => true]);
        $creator->method('prepareState')->willReturn($sizeState);
        $success = $this->createStub(AttributeSynchronizationResultInterface::class);
        $success->method('isSuccessful')->willReturn(true);
        $success->method('getStatus')->willReturn(AttributeSynchronizationResultInterface::STATUS_SUCCESS);
        $publisher = $this->createMock(AttributeDefinitionPublisherInterface::class);
        $publisher->expects(self::once())->method('publishBatch')
            ->with([$sizeState])->willReturn(['size' => $success]);

        $items = [
            ['code' => 'color', 'label' => 'Color', 'target_type' => 'text'],
            ['code' => 'color', 'label' => 'Colour', 'target_type' => 'text'],
            ['code' => 'size', 'label' => 'Size', 'target_type' => 'numeric'],
        ];
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturnMap([['items', '', (new Json())->serialize($items)]]);
        $result = $this->createMock(JsonResult::class);
        $result->expects(self::once())->method('setData')->with(self::callback(static function (array $data): bool {
            self::assertTrue($data['success']);
            self::assertSame(['failed', 'failed', 'synchronized'], array_column($data['items'], 'status'));
            self::assertSame(['processed' => 3, 'successful' => 1, 'failed' => 2], $data['stats']);

            return true;
        }))->willReturnSelf();
        $resultFactory = $this->createStub(ResultFactory::class);
        $resultFactory->method('create')->willReturn($result);
        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getResultFactory')->willReturn($resultFactory);

        $batchPublisher = new AttributeBatchPublisher(
            $creator,
            $publisher,
            $this->createStub(SynchronizationRateLimitGuard::class)
        );
        self::assertSame($result, (new Create($context, new Json(), $batchPublisher))->execute());
    }
}
