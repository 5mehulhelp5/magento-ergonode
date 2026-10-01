<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeHistory\Api;

interface HistoryActorProviderInterface
{
    /** @return array{actor_id: int|null, actor_name: string|null} */
    public function getActor(): array;
}
