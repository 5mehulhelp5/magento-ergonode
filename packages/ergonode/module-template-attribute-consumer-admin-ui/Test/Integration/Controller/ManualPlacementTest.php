<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumerAdminUi\Test\Integration\Controller;

use Ergonode\TemplateAttributeConsumer\Api\ManualPlacementSaverInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http;
use Magento\Framework\Data\Form\FormKey;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\TestCase\AbstractBackendController;

#[AppArea('adminhtml')]
#[AppIsolation(true)]
class ManualPlacementTest extends AbstractBackendController
{
    protected $resource = 'Ergonode_TemplateConsumer::template_save';
    protected $uri = 'backend/ergonode/template/manualPlacement';
    protected $httpMethod = 'POST';

    protected function setUp(): void
    {
        parent::setUp();
        $this->httpRequest()->setMethod('POST')->setPostValue([
            'form_key' => $this->_objectManager->get(FormKey::class)->getFormKey(),
        ]);
        $this->httpRequest()->getHeaders()->addHeaderLine('X-Requested-With', 'XMLHttpRequest');
    }

    public function testValidPostDelegatesToSaver(): void
    {
        $saver = $this->createMock(ManualPlacementSaverInterface::class);
        $saver->expects(self::once())->method('save')->with('template-test', 4, 21, true);
        $this->_objectManager->addSharedInstance($saver, ManualPlacementSaverInterface::class, true);
        $this->httpRequest()->setPostValue([
            'form_key' => $this->_objectManager->get(FormKey::class)->getFormKey(),
            'template_code' => 'template-test',
            'attribute_set_id' => '4',
            'attribute_id' => '21',
            'manual' => '1',
        ]);

        $this->dispatch($this->uri);

        $response = $this->getResponse();
        self::assertInstanceOf(Http::class, $response);
        self::assertSame(200, $response->getHttpResponseCode());
        self::assertSame(
            ['success' => true],
            json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    public function testInvalidManualValueCannotInvokeSaver(): void
    {
        $saver = $this->createMock(ManualPlacementSaverInterface::class);
        $saver->expects(self::never())->method('save');
        $this->_objectManager->addSharedInstance($saver, ManualPlacementSaverInterface::class, true);
        $this->httpRequest()->setPostValue([
            'form_key' => $this->_objectManager->get(FormKey::class)->getFormKey(),
            'manual' => 'invalid',
        ]);

        $this->dispatch($this->uri);

        $response = $this->getResponse();
        self::assertInstanceOf(Http::class, $response);
        self::assertSame(200, $response->getHttpResponseCode());
        $body = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($body['success']);
    }

    public function testInvalidFormKeyCannotInvokeSaver(): void
    {
        $saver = $this->createMock(ManualPlacementSaverInterface::class);
        $saver->expects(self::never())->method('save');
        $this->_objectManager->addSharedInstance($saver, ManualPlacementSaverInterface::class, true);
        $this->httpRequest()->setPostValue(['form_key' => 'invalid', 'manual' => '1']);

        $this->dispatch($this->uri);

        $response = $this->getResponse();
        self::assertInstanceOf(Http::class, $response);
        self::assertNotSame(200, $response->getHttpResponseCode());
    }

    private function httpRequest(): HttpRequest
    {
        $request = $this->getRequest();
        self::assertInstanceOf(HttpRequest::class, $request);

        return $request;
    }
}
