<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeHistoryAdminUi\Test\Integration\Controller;

use Ergonode\CategoryAttributeHistory\Api\HistoryOperationCaptureInterface;
use Magento\Framework\App\Response\Http;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * @magentoAppArea adminhtml
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class HistoryTest extends AbstractBackendController
{
    protected $resource = 'Ergonode_CategoryAttributeHistory::view';
    protected $uri = 'backend/ergonode/category_attribute_history/operations';
    protected $httpMethod = 'GET';

    public function testAuthenticatedRouteReturnsRecordedActorAndOperation(): void
    {
        $this->_objectManager->get(HistoryOperationCaptureInterface::class)
            ->execute('save', static fn (): array => []);
        $this->dispatch($this->uri);
        $response = $this->getResponse();
        self::assertInstanceOf(Http::class, $response);
        self::assertSame(200, $response->getHttpResponseCode());
        $data = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $operation = $data['page']['items'][0];
        self::assertSame('save', $operation['operation_code']);
        self::assertSame('admin', $operation['origin']);
        $user = $this->_auth->getUser();
        self::assertTrue(method_exists($user, 'getId'));
        self::assertSame((int)$user->getId(), $operation['actor_id']);
        self::assertNotEmpty($operation['actor_name']);
    }

    public function testStateEndpointRejectsMissingOperation(): void
    {
        $this->dispatch('backend/ergonode/category_attribute_history/state');
        $response = $this->getResponse();
        self::assertInstanceOf(Http::class, $response);
        self::assertSame(400, $response->getHttpResponseCode());
        $data = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('An operation is required.', $data['message']);
    }
}
