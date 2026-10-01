<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistory\Model\Context;

use Ergonode\CategoryConsumerHistory\Api\HistoryActorProviderInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Throwable;

class HistoryExecutionContext
{
    public function __construct(
        private readonly State $appState,
        private readonly HistoryActorProviderInterface $actorProvider
    ) {
    }

    /** @return array{origin: string, actor_id: int|null, actor_name: string|null} */
    public function get(): array
    {
        try {
            $areaCode = $this->appState->getAreaCode();
        } catch (Throwable) {
            $areaCode = '';
        }
        $origin = match ($areaCode) {
            Area::AREA_ADMINHTML => 'admin',
            Area::AREA_CRONTAB => 'cron',
            default => 'cli',
        };

        return ['origin' => $origin] + $this->actorProvider->getActor();
    }
}
