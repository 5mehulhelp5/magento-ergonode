<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\Index;

use Ergonode\Media\Api\ScanStatusProviderInterface;
use Ergonode\Media\Model\Port\ScanStateInterface;

class ScanStatusProvider implements ScanStatusProviderInterface
{
    public function __construct(private readonly ScanStateInterface $state, private readonly ScanReadiness $readiness)
    {
    }

    public function getStatus(): array
    {
        $row = $this->state->read();
        if ($row['status'] === 'required') {
            $row['estimated_total'] = $this->state->estimate();
        }
        $processed = $row['indexed'] + $row['reused'];
        $running = in_array($row['status'], ['running', 'auditing'], true);
        $end = $running ? time() : ($row['updated_at'] ?? 0);
        $elapsed = $row['started_at'] === null ? 0 : max(0, $end - $row['started_at']);
        $remaining = $running && $elapsed >= 5 && $processed >= 10 && $row['estimated_total'] > $processed
            ? (int)ceil(($row['estimated_total'] - $processed) * $elapsed / $processed) : null;
        $percent = $row['estimated_total'] > 0
            ? min(99, (int)floor(100 * $processed / $row['estimated_total'])) : null;
        return $row + [
            'processed' => $processed,
            'elapsed_seconds' => $elapsed,
            'estimated_remaining_seconds' => $remaining,
            'percent' => in_array($row['status'], ['complete', 'audited'], true) ? 100 : $percent,
            'blocked' => $this->readiness->isBlocked(),
            'verification_completed_at' => $row['verification_completed_at'] ?? null,
        ];
    }
}
