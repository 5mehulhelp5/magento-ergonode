<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Test\Integration\Controller\Adminhtml\Synchronization;

use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * @magentoAppArea adminhtml
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class StatusTest extends AbstractBackendController
{
    protected $resource = 'Ergonode_Core::synchronizations';
    protected $uri = 'backend/ergonode/synchronization/status';
    protected $httpMethod = 'POST';

    protected function setUp(): void
    {
        parent::setUp();
        $request = $this->getRequest();
        self::assertInstanceOf(HttpRequest::class, $request);
        $request->setPostValue('form_key', $this->_objectManager->get(FormKey::class)->getFormKey());
        $request->getHeaders()->addHeaderLine('X-Requested-With', 'XMLHttpRequest');
    }

    public function testReturnsUncachedStatusThroughAuthenticatedMagentoRouting(): void
    {
        $request = $this->getRequest();
        self::assertInstanceOf(HttpRequest::class, $request);
        $request->setMethod('POST');
        $this->dispatch($this->uri);
        $response = $this->getResponse();
        self::assertInstanceOf(HttpResponse::class, $response);
        self::assertSame(200, $response->getHttpResponseCode());
        $data = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($data['success']);
        self::assertIsArray($data['processes']);
        self::assertNotFalse(strtotime($data['observed_at']));
        self::assertStringContainsString('no-store', $response->getHeader('Cache-Control')->getFieldValue());
        self::assertNotSame(PHP_SESSION_ACTIVE, session_status());
    }
}
