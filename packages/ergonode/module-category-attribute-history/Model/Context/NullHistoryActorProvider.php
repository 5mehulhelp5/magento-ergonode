<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeHistory\Model\Context;

use Ergonode\CategoryAttributeHistory\Api\HistoryActorProviderInterface;

class NullHistoryActorProvider implements HistoryActorProviderInterface
{
    public function getActor(): array
    {
        return ['actor_id' => null, 'actor_name' => null];
    }
}
