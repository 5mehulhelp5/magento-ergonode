<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisherAdminUi\Test\Unit\Model;

use Ergonode\ProductPublisherAdminUi\Model\PublishGridAction;
use Ergonode\PublisherAdminUi\Api\WriteReadinessProviderInterface;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\AuthorizationInterface;
use PHPUnit\Framework\TestCase;

class PublishGridActionTest extends TestCase
{
    public function testProductActionKeepsGraphQlReadinessWithoutRestLoginPreflight(): void
    {
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->expects(self::once())->method('isAllowed')
            ->with('Ergonode_ProductPublisher::publish')->willReturn(true);
        $url = $this->createMock(UrlInterface::class);
        $url->expects(self::once())->method('getUrl')
            ->with('ergonode_product_publisher/publication/publish')->willReturn('/publish');
        $readiness = $this->createMock(WriteReadinessProviderInterface::class);
        $readiness->expects(self::once())->method('getStatus')->willReturn([
            'ready' => true,
            'message' => '',
            'configuration_url' => '/configuration',
        ]);

        $action = (new PublishGridAction($authorization, $url, $readiness))->getConfiguration();

        self::assertSame('/publish', $action['url']);
        self::assertTrue($action['ready']);
        self::assertArrayNotHasKey('preflight', $action);
    }
}
