<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerAdminUi\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;

class CategorySettingsSaved implements ObserverInterface
{
    public function __construct(private readonly ManagerInterface $messageManager)
    {
    }

    public function execute(Observer $observer): void
    {
        foreach ((array)$observer->getEvent()->getData('changed_paths') as $path) {
            if (str_contains((string)$path, '/synchronization/')) {
                $this->messageManager->addNoticeMessage(__(
                    'Use Sync (force) in Categories to apply changed settings to existing categories.'
                ));
                return;
            }
        }
    }
}
