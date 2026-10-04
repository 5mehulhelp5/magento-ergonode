<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\Index;

use Ergonode\Media\Api\ScanRequesterInterface;
use Ergonode\Media\Model\Port\ScanStateInterface;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Exception\LocalizedException;

class ScanRequest implements ScanRequesterInterface
{
    public function __construct(
        private readonly ScanStateInterface $state,
        private readonly LockManagerInterface $locks
    ) {
    }

    public function request(bool $verifyContent = false): void
    {
        $pending = $verifyContent ? 'audit_pending' : 'pending';
        $running = $verifyContent ? 'auditing' : 'running';
        if (!$this->locks->lock(LocalFileScanner::LOCK, 0)) {
            if (in_array($this->state->read()['status'], [$pending, $running], true)) {
                return;
            }
            throw new LocalizedException(__('The scanner is busy. Refresh the status and try again.'));
        }
        try {
            $status = $this->state->read()['status'];
            if (in_array($status, ['pending', 'audit_pending'], true) && $status !== $pending) {
                throw new LocalizedException(__('Another local media scan is already requested.'));
            }
            if ($status !== $pending) {
                if ($verifyContent) {
                    $this->state->request($this->state->estimate(), true);
                } else {
                    $this->state->request($this->state->estimate());
                }
            }
        } finally {
            $this->locks->unlock(LocalFileScanner::LOCK);
        }
    }
}
