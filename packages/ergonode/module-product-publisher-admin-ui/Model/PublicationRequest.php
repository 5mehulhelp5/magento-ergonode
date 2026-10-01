<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisherAdminUi\Model;

use Ergonode\ProductAdminUi\Api\ProductSelectionInterface;
use Ergonode\ProductPublisher\Api\ProductPublicationBatchInterface;
use Ergonode\PublisherAdminUi\Api\WriteReadinessProviderInterface;
use Magento\Framework\Exception\LocalizedException;

class PublicationRequest
{
    public function __construct(
        private readonly ProductSelectionInterface $selection,
        private readonly ProductPublicationBatchInterface $batch,
        private readonly WriteReadinessProviderInterface $readiness
    ) {
    }

    /** @return list<array{product_id: int, code: string, status: string, message: string, publication_at: string}> */
    public function publish(string $payload): array
    {
        $status = $this->readiness->getStatus();
        if (!$status['ready']) {
            throw new LocalizedException(__($status['message']));
        }

        return $this->batch->publish($this->selection->decodeIds($payload));
    }
}
