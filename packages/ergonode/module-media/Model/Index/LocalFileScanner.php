<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\Index;

use Ergonode\Media\Model\Queue\QueuePublisher;
use Ergonode\Media\Model\Port\LocalFileIndexInterface;
use Ergonode\Media\Model\Port\ScanStateInterface;
use Throwable;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;

class LocalFileScanner
{
    public const string LOCK = 'ergonode_media_index_scan';

    public function __construct(
        private readonly QueuePublisher $publisher,
        private readonly LocalFiles $files,
        private readonly LocalFileIndexInterface $index,
        private readonly LockManagerInterface $locks,
        private readonly ScanStateInterface $state
    ) {
    }

    /** @return array{indexed:int,reused:int,removed:int} */
    public function scan(bool $onlyRequested = false): array
    {
        $result = ['indexed' => 0, 'reused' => 0, 'removed' => 0];
        if (!$this->locks->lock(self::LOCK, 0)) {
            if ($onlyRequested) {
                return $result;
            }
            throw new LocalizedException(__('A local media scan is already running.'));
        }
        $bytes = 0;
        $lastProgress = microtime(true);
        try {
            if ($onlyRequested && !in_array($this->state->read()['status'], ['pending', 'running'], true)) {
                return $result;
            }
            $this->state->begin($this->state->estimate());
            foreach ($this->files->paths() as $path) {
                $stat = $this->files->stat($path);
                if ($stat === null) {
                    continue;
                }
                $previous = $this->index->get($path);
                if ($previous !== null && $previous['size'] === $stat['size']
                    && $previous['modified_at'] === $stat['modified_at']
                ) {
                    $result['reused']++;
                } else {
                    $hash = $this->files->hash($path);
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
                    if ($this->files->stat($after) === null) {
                        $this->index->remove($after);
                        $result['removed']++;
                    }
                }
            } while ($page !== []);
            $this->state->progress($result, $bytes);
            $this->state->complete();
        } catch (Throwable $exception) {
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
