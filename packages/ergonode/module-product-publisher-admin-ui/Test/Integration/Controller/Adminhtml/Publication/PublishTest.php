<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisherAdminUi\Test\Integration\Controller\Adminhtml\Publication;

use Ergonode\ProductPublisherAdminUi\Model\PublicationRequest;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Data\Form\FormKey;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * @magentoAppArea adminhtml
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class PublishTest extends AbstractBackendController
{
    protected $resource = 'Ergonode_ProductPublisher::publish';
    protected $uri = 'backend/ergonode_product_publisher/publication/publish';
    protected $httpMethod = 'POST';

    protected function setUp(): void
    {
        parent::setUp();
        $this->httpRequest()->setMethod('POST')->setPostValue([
            'form_key' => Bootstrap::getObjectManager()->get(FormKey::class)->getFormKey(),
        ]);
        $this->httpRequest()->getHeaders()->addHeaderLine('X-Requested-With', 'XMLHttpRequest');
    }

    public function testValidPostReachesPublicationRequest(): void
    {
        $service = $this->createMock(PublicationRequest::class);
        $service->expects(self::once())->method('publish')->with('[7]')->willReturn([]);
        $this->registerService($service);
        $this->httpRequest()->setPostValue('ids', '[7]');

        $this->dispatch($this->uri);

        $response = $this->getResponse();
        self::assertInstanceOf(HttpResponse::class, $response);
        self::assertSame(200, $response->getHttpResponseCode());
        self::assertSame(
            ['success' => true, 'items' => []],
            json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    public function testInvalidFormKeyNeverInvokesPublication(): void
    {
        $service = $this->createMock(PublicationRequest::class);
        $service->expects(self::never())->method('publish');
        $this->registerService($service);
        $this->httpRequest()->setPostValue(['form_key' => 'invalid', 'ids' => '[7]']);

        $this->dispatch($this->uri);

        $response = $this->getResponse();
        self::assertInstanceOf(HttpResponse::class, $response);
        self::assertNotSame(200, $response->getHttpResponseCode());
    }

    private function httpRequest(): HttpRequest
    {
        $request = $this->getRequest();
        self::assertInstanceOf(HttpRequest::class, $request);

        return $request;
    }

    private function registerService(PublicationRequest $service): void
    {
        $manager = Bootstrap::getObjectManager();
        self::assertInstanceOf(ObjectManager::class, $manager);
        $manager->addSharedInstance($service, PublicationRequest::class, true);
    }
}
