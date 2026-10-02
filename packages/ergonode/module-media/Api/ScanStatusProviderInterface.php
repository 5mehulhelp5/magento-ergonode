<?php

declare(strict_types=1);

namespace Ergonode\Media\Api;

interface ScanStatusProviderInterface
{
    /**
     * Read persisted scan progress. Percent and remaining time are estimates until completion.
     *
     * @return array{status:string,estimated_total:int,indexed:int,reused:int,removed:int,bytes:int,
     *     started_at:?int,updated_at:?int,last_completed_at:?int,error:?string,processed:int,
     *     elapsed_seconds:int,estimated_remaining_seconds:?int,percent:?int,blocked:bool}
     */
    public function getStatus(): array;
}
