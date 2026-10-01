<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\Source;

use Psr\Log\LoggerInterface;

class ProductSourceLoader
{
    public function __construct(
        private readonly MagentoProductSourceProvider $sourceProvider,
        private readonly ProductSourceStateBuilder $stateBuilder,
        private readonly LoggerInterface $logger
    ) {
    }

    /** @param string[] $selectedSkus */
    public function load(array $selectedSkus = []): ProductSourceResult
    {
        $result = $this->stateBuilder->build($this->sourceProvider->load($selectedSkus), $selectedSkus);
        foreach ($result->getSkippedProductMessages() as $sku => $message) {
            $this->logger->error($message, ['sku' => $sku]);
        }

        return $result;
    }
}
