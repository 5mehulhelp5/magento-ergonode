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

    public function request(): void
    {
        if (!$this->locks->lock(LocalFileScanner::LOCK, 0)) {
            if (in_array($this->state->read()['status'], ['pending', 'running'], true)) {
                return;
            }
            throw new LocalizedException(__('The scanner is busy. Refresh the status and try again.'));
        }
        try {
            if ($this->state->read()['status'] !== 'pending') {
                $this->state->request($this->state->estimate());
            }
        } finally {
            $this->locks->unlock(LocalFileScanner::LOCK);
        }
    }
}
