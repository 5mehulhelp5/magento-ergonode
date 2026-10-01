<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeHistory\Model\Context;

use Ergonode\CategoryAttributeHistory\Api\HistoryActorProviderInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;

class ExecutionContext
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
            $area = $this->appState->getAreaCode();
        } catch (LocalizedException) {
            $area = '';
        }

        return ['origin' => match ($area) {
            Area::AREA_ADMINHTML => 'admin',
            Area::AREA_CRONTAB => 'cron',
            default => 'cli',
        }] + $this->actorProvider->getActor();
    }
}
