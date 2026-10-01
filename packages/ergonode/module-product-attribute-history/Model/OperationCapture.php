<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeHistory\Model;

use Ergonode\ProductAttributeHistory\Api\HistoryOperationCaptureInterface;
use Ergonode\ProductAttributeHistory\Model\Context\ExecutionContext;
use Ergonode\ProductAttributeHistory\Model\Persistence\HistoryWriterInterface;
use Ergonode\ProductAttributeHistory\Model\Config\HistoryConfig;
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

    /** @return array<string, mixed>|null */
    private function snapshot(): ?array
    {
        try {
            return $this->snapshotProvider->getState();
        } catch (Throwable $exception) {
            $this->logger->error('Unable to capture product attribute history state.', ['exception' => $exception]);
            return null;
        }
    }

    /** @param array<string, mixed>|null $before */
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
            $this->logger->error('Unable to save product attribute history.', ['exception' => $exception]);
        }
    }
}
