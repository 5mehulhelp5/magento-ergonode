<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeHistory\Model;

use Ergonode\CategoryAttributeHistory\Api\HistoryOperationCaptureInterface;
use Ergonode\CategoryAttributeHistory\Model\Context\ExecutionContext;
use Ergonode\CategoryAttributeHistory\Model\Persistence\HistoryWriterInterface;
use Ergonode\CategoryAttributeHistory\Model\Config\HistoryConfig;
use Psr\Log\LoggerInterface;
use Throwable;

class OperationCapture implements HistoryOperationCaptureInterface
{
    private bool $capturing = false;

    public function __construct(
        private readonly SnapshotProvider $snapshotProvider,
        private readonly StateDiffer $differ,
        private readonly HistoryWriterInterface $writer,
        private readonly ExecutionContext $executionContext,
        private readonly LoggerInterface $logger,
        private readonly HistoryConfig $config
    ) {
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function execute(string $code, callable $operation): mixed
    {
        if ($this->capturing || !$this->config->isEnabled()) {
            return $operation();
        }
        $this->capturing = true;
        $startedAt = gmdate('Y-m-d H:i:s');
        $before = $this->snapshot();
        $status = 'success';
        try {
            return $operation();
        } catch (Throwable $exception) {
            $status = 'failed';
            throw $exception;
        } finally {
            try {
                $this->record($code, $status, $startedAt, $before);
            } finally {
                $this->capturing = false;
            }
        }
    }

    /** @return array{source: list<array<string, mixed>>, target: list<array<string, mixed>>}|null */
    private function snapshot(): ?array
    {
        try {
            return $this->snapshotProvider->getState();
        } catch (Throwable $exception) {
            $this->logger->error('Unable to capture category attribute history state.', ['exception' => $exception]);
            return null;
        }
    }

    /** @param array{source: list<array<string, mixed>>, target: list<array<string, mixed>>}|null $before */
    private function record(string $code, string $status, string $startedAt, ?array $before): void
    {
        try {
            $after = $this->snapshot();
            if ($before === null || $after === null) {
                return;
            }
            $changes = $this->differ->compare($before, $after);
            $this->writer->save([
                'operation_code' => $code,
                'status' => $status,
                'started_at' => $startedAt,
                'finished_at' => gmdate('Y-m-d H:i:s'),
                'change_count' => count($changes),
                'state' => $after,
                'changes' => $changes,
            ] + $this->executionContext->get());
        } catch (Throwable $exception) {
            $this->logger->error('Unable to save category attribute history.', ['exception' => $exception]);
        }
    }
}
