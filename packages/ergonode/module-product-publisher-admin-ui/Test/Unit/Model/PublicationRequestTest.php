<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisherAdminUi\Test\Unit\Model;

use Ergonode\ProductPublisher\Api\ProductPublicationBatchInterface;
use Ergonode\ProductAdminUi\Api\ProductSelectionInterface;
use Ergonode\ProductPublisherAdminUi\Model\PublicationRequest;
use Ergonode\PublisherAdminUi\Api\WriteReadinessProviderInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class PublicationRequestTest extends TestCase
{
    public function testUnavailableWriteConfigurationBlocksPublicationBeforeAnyMutation(): void
    {
        $batch = $this->createMock(ProductPublicationBatchInterface::class);
        $batch->expects(self::never())->method('publish');
        $readiness = $this->createStub(WriteReadinessProviderInterface::class);
        $readiness->method('getStatus')->willReturn([
            'ready' => false, 'message' => 'Read only.', 'configuration_url' => '',
        ]);
        $request = new PublicationRequest(
            $this->createStub(ProductSelectionInterface::class),
            $batch,
            $readiness
        );
        $this->expectException(LocalizedException::class);
        $request->publish('[1]');
    }

    public function testPublicationUsesValidatedSelection(): void
    {
        $selection = $this->createMock(ProductSelectionInterface::class);
        $selection->expects(self::once())->method('decodeIds')->with('[7]')->willReturn([7]);
        $batch = $this->createMock(ProductPublicationBatchInterface::class);
        $batch->expects(self::once())->method('publish')->with([7])->willReturn([]);
        $readiness = $this->createStub(WriteReadinessProviderInterface::class);
        $readiness->method('getStatus')->willReturn(['ready' => true, 'message' => '', 'configuration_url' => '']);
        self::assertSame([], (new PublicationRequest($selection, $batch, $readiness))->publish('[7]'));
    }
}
