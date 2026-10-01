<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeAdminUi\Test\Integration\Controller;

use Magento\Framework\App\Response\Http;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\TestCase\AbstractBackendController;

#[AppArea('adminhtml')]
#[AppIsolation(true)]
class StructureTest extends AbstractBackendController
{
    protected $resource = 'Ergonode_TemplateConsumer::template_mapping';
    protected $uri = 'backend/ergonode/template/structure';
    protected $httpMethod = 'GET';

    public function testUnknownPairReturnsControlledError(): void
    {
        $this->getRequest()->setParams([
            'template_code' => 'missing-playwright-template',
            'attribute_set_id' => 0,
        ]);
        $this->dispatch($this->uri);

        $response = $this->getResponse();
        self::assertInstanceOf(Http::class, $response);
        self::assertSame(200, $response->getHttpResponseCode());
        $body = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($body['success']);
        self::assertSame('The saved template mapping is no longer available. Reload the view.', $body['message']);
    }
}
