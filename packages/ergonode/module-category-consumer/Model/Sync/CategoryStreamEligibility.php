<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Sync;

use Ergonode\CategoryConsumer\Api\CategoryStreamAvailabilityProviderInterface;
use Ergonode\CategoryConsumer\Api\CategoryTreeReadinessProviderInterface;
use Ergonode\CategoryConsumer\Model\Config\CategoryConfigProvider;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Ergonode\CategoryConsumer\Api\CategoryCreationConfigurationProviderInterface;

class CategoryStreamEligibility implements CategoryStreamAvailabilityProviderInterface
{
    public function __construct(
        private readonly CategoryConfigProvider $configProvider,
        private readonly CategoryTreeReadinessProviderInterface $treeProvider,
        private readonly CategoryCreationConfigurationProviderInterface $creationConfiguration
    ) {
    }

    /** @throws LocalizedException */
    public function assertCanSynchronize(bool $data = false): void
    {
        $reason = $this->getBlockingReason($data);
        if ($reason !== null) {
            throw new LocalizedException($reason);
        }
    }

    public function canRunCron(bool $data = false): bool
    {
        $cronEnabled = $data
            ? $this->configProvider->isDataCronEnabled()
            : $this->configProvider->isCronEnabled();

        return $cronEnabled && $this->getBlockingReason($data) === null;
    }

    public function getBlockingReason(bool $data = false): ?Phrase
    {
        if (!$this->configProvider->isEnabled()) {
            return __('Ergonode category synchronization is disabled.');
        }
        if ($data && !$this->configProvider->isDataSynchronizationEnabled()) {
            return __('Category data synchronization is disabled.');
        }
        if ($this->treeProvider->getActiveTrees() === []) {
            return __('Enable at least one category tree mapping before synchronizing.');
        }

        if (!$data) {
            try {
                $this->creationConfiguration->get();
            } catch (LocalizedException $exception) {
                return new Phrase($exception->getRawMessage(), $exception->getParameters());
            }
        }

        return null;
    }
}
