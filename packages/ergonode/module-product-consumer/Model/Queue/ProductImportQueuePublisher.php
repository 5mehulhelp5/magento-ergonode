<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Queue;

use Magento\Framework\MessageQueue\PublisherInterface;

class ProductImportQueuePublisher
{
    private const string TOPIC = 'ergonode.product.import';

    public function __construct(private readonly PublisherInterface $publisher)
    {
    }

    public function dispatch(): void
    {
        $this->publisher->publish(self::TOPIC, 'drain');
    }
}
