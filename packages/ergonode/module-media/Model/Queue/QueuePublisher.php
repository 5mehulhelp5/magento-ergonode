<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\Queue;

use Magento\Framework\MessageQueue\PublisherInterface;

class QueuePublisher
{
    public function __construct(private readonly PublisherInterface $publisher)
    {
    } public function dispatch(): void
    {
        $this->publisher->publish('ergonode.media.gallery', 'drain');
    }
}
