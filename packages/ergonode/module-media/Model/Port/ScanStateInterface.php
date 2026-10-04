<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\Port;

/** @phpstan-type ScanRecord array{status:string,estimated_total:int,indexed:int,reused:int,removed:int,bytes:int,started_at:?int,updated_at:?int,last_completed_at:?int,verification_completed_at:?int,error:?string} */
interface ScanStateInterface
{
    /** @return ScanRecord */
    public function read(): array;

    public function estimate(): int;

    public function request(int $estimate, bool $verifyContent = false): void;

    public function begin(int $estimate, bool $verifyContent = false): void;

    /** @param array{indexed:int,reused:int,removed:int} $counts */
    public function progress(array $counts, int $bytes): void;

    /** Audit findings are kept in the existing error/report field; audit does not certify import readiness. */
    public function complete(bool $verifyContent = false, ?string $report = null): void;

    public function fail(string $error): void;
}
