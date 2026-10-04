<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\Index;

use Ergonode\Media\Model\Queue\QueuePublisher;
use Ergonode\Media\Model\Port\LocalFileIndexInterface;
use Ergonode\Media\Model\Port\ScanStateInterface;
use Throwable;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use Psr\Log\LoggerInterface;

class LocalFileScanner
{
    public const string LOCK = 'ergonode_media_index_scan';

    public function __construct(
        private readonly QueuePublisher $publisher,
        private readonly LocalFiles $files,
        private readonly LocalFileIndexInterface $index,
        private readonly LockManagerInterface $locks,
        private readonly ScanStateInterface $state,
        private readonly ?MaterializationIntegrityVerifier $verifier = null,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /** @return array{indexed:int,reused:int,removed:int,mismatched?:int,missing?:int,unreadable?:int} */
    public function scan(bool $onlyRequested = false, bool $verifyContent = false): array
    {
        $result = ['indexed' => 0, 'reused' => 0, 'removed' => 0];
        if (!$this->locks->lock(self::LOCK, 0)) {
            if ($onlyRequested) {
                return $result;
            }
            throw new LocalizedException(__('A local media scan is already running.'));
        }
        $bytes = 0;
        $unreadable = [];
        $verifiedHashes = [];
        $lastProgress = microtime(true);
        try {
            if ($onlyRequested) {
                $status = $this->state->read()['status'];
                if ($status === 'auditing') {
                    // Owning the lock while the previous run is marked active means it was interrupted.
                    $message = (string)__('File verification was interrupted. Request a new verification manually.');
                    $this->state->fail($message);
                    $this->logger?->error($message);
                    return $result;
                }
                if (!in_array($status, ['pending', 'running', 'audit_pending'], true)) {
                    return $result;
                }
                $verifyContent = $status === 'audit_pending';
            }
            if ($verifyContent && $this->verifier === null) {
                throw new LocalizedException(__('Local media integrity verifier is unavailable.'));
            }
            if ($verifyContent) {
                $this->state->begin($this->state->estimate(), true);
            } else {
                $this->state->begin($this->state->estimate());
            }
            foreach ($this->files->paths() as $path) {
                if ($verifyContent) {
                    try {
                        $stat = $this->files->stat($path);
                        $hash = $stat === null ? null : $this->files->hash($path);
                    } catch (Throwable $exception) {
                        $unreadable[$path] = true;
                        $this->logger?->error('Unable to read local media during integrity verification.', [
                            'path' => $path, 'exception' => $exception,
                        ]);
                        continue;
                    }
                } else {
                    $stat = $this->files->stat($path);
                }
                if ($stat === null) {
                    continue;
                }
                $previous = $this->index->get($path);
                if (!$verifyContent && $previous !== null && $previous['size'] === $stat['size']
                    && $previous['modified_at'] === $stat['modified_at']
                ) {
                    $result['reused']++;
                } else {
                    if (!$verifyContent) {
                        $hash = $this->files->hash($path);
                    }
                    if ($verifyContent) {
                        $verifiedHashes[$path] = $hash;
                    }
                    $this->index->save($path, $hash, $stat['size'], $stat['modified_at']);
                    $result['indexed']++;
                }
                $bytes += $stat['size'];
                if (microtime(true) - $lastProgress >= 1 || ($result['indexed'] + $result['reused']) % 100 === 0) {
                    $this->state->progress($result, $bytes);
                    $lastProgress = microtime(true);
                }
            }
            $after = '';
            do {
                $page = $this->index->page($after, 200);
                foreach ($page as $row) {
                    $after = $row['path'];
                    if (isset($unreadable[$after])) {
                        continue;
                    }
                    try {
                        $stat = $this->files->stat($after);
                    } catch (Throwable $exception) {
                        if (!$verifyContent) {
                            throw $exception;
                        }
                        $unreadable[$after] = true;
                        $this->logger?->error('Unable to inspect indexed media during integrity verification.', [
                            'path' => $after, 'exception' => $exception,
                        ]);
                        continue;
                    }
                    if ($stat === null) {
                        $this->index->remove($after);
                        $result['removed']++;
                    }
                }
            } while ($page !== []);
            $this->state->progress($result, $bytes);
            if ($verifyContent) {
                $issues = $this->verifier->verify($unreadable, $verifiedHashes);
                $issues['unreadable'] += count($unreadable);
                $report = array_sum($issues) === 0 ? null : (string)__(
                    'Verification completed: %1 content mismatches, %2 missing files, %3 unreadable files. See logs for paths and details. No files were repaired.',
                    $issues['mismatched'], $issues['missing'], $issues['unreadable']
                );
                $this->state->complete(true, $report);
                return $result + $issues;
            }
            $this->state->complete();
        } catch (Throwable $exception) {
            $this->logger?->error('Unable to complete local media scan.', [
                'verification' => $verifyContent, 'exception' => $exception,
            ]);
            $this->state->progress($result, $bytes);
            $this->state->fail($exception->getMessage());
            throw $exception;
        } finally {
            $this->locks->unlock(self::LOCK);
        }

        $this->publisher->dispatch();
        return $result;
    }
}
