<?php

declare(strict_types=1);

namespace Ergonode\TemplatePublisherAdminUi\Block\Adminhtml\Test\Unit\Template;

use Ergonode\TemplatePublisherAdminUi\Controller\Adminhtml\Template\Create;
use Ergonode\TemplatePublisherAdminUi\Model\TemplateFromAttributeSetCreator;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class CreateTest extends TestCase
{
    public function testCreatesTemplateFromRequestAndReturnsIt(): void
    {
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturnMap([
            ['template_code', '', 'summer_collection'],
            ['attribute_set_id', 0, 12],
        ]);
        $creator = $this->createMock(TemplateFromAttributeSetCreator::class);
        $creator->expects(self::once())
            ->method('create')
            ->with('summer_collection', 12)
            ->willReturn(['code' => 'summer_collection', 'name' => 'Summer Collection']);
        $json = $this->createMock(Json::class);
        $json->expects(self::once())
            ->method('setData')
            ->with(self::callback(static fn (array $data): bool => $data['success'] === true
                && $data['template']['code'] === 'summer_collection'))
            ->willReturnSelf();
        $resultFactory = $this->createMock(ResultFactory::class);
        $resultFactory->method('create')->with(ResultFactory::TYPE_JSON)->willReturn($json);
        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getResultFactory')->willReturn($resultFactory);

        $controller = new Create($context, $creator, new NullLogger());

        self::assertSame($json, $controller->execute());
    }
}
