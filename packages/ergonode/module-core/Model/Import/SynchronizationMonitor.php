<?php

declare(strict_types=1);

namespace Ergonode\Core\Model\Import;

use Ergonode\Core\Api\SynchronizationMonitorInterface;
use Ergonode\Core\Api\SynchronizationObservationProviderInterface;
use Ergonode\Core\Api\SynchronizationStatusProviderInterface;

class SynchronizationMonitor implements SynchronizationMonitorInterface
{
    /** @param array<string, SynchronizationObservationProviderInterface> $observationProviders */
    public function __construct(
        private readonly SynchronizationStatusProviderInterface $statusProvider,
        private readonly CursorStorage $cursorStorage,
        private readonly array $observationProviders = []
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function getList(): array
    {
        $items = $this->statusProvider->getList();
        foreach ($items as &$item) {
            $item['reset_at'] = $this->cursorStorage->getResetAt($item['process_code']);
            if (isset($this->observationProviders[$item['process_code']])) {
                $item['observation'] = $this->observationProviders[$item['process_code']]->getStatus();
            }
        }
        unset($item);

        return $items;
    }
}
