<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumerAdminUi\Test\Unit\Model;

use Ergonode\ProductConsumer\Api\ProductImportReadinessInterface;
use Ergonode\ProductConsumerAdminUi\Model\ImportGridAction;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\AuthorizationInterface;
use PHPUnit\Framework\TestCase;

class ImportGridActionTest extends TestCase
{
    public function testDeniedPermissionDoesNotLoadImportReadiness(): void
    {
        $authorization = $this->createStub(AuthorizationInterface::class);
        $authorization->method('isAllowed')->willReturn(false);
        $readiness = $this->createMock(ProductImportReadinessInterface::class);
        $readiness->expects(self::never())->method('getStatus');
        $action = new ImportGridAction($authorization, $this->createStub(UrlInterface::class), $readiness);
        self::assertNull($action->getConfiguration());
    }

    public function testReadyActionRequiresMappingAndUsesItsOwnEndpoint(): void
    {
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->expects(self::once())->method('isAllowed')
            ->with('Ergonode_ProductConsumer::import')->willReturn(true);
        $readiness = $this->createStub(ProductImportReadinessInterface::class);
        $readiness->method('getStatus')->willReturn(['ready' => true, 'message' => '']);
        $url = $this->createMock(UrlInterface::class);
        $url->expects(self::once())->method('getUrl')
            ->with('ergonode_product_consumer/product/import')->willReturn('/import');
        $configuration = (new ImportGridAction($authorization, $url, $readiness))->getConfiguration();
        self::assertNotNull($configuration);
        self::assertTrue($configuration['requires_mapping']);
        self::assertTrue($configuration['primary']);
        self::assertTrue($configuration['ready']);
        self::assertSame('/import', $configuration['url']);
    }
}
