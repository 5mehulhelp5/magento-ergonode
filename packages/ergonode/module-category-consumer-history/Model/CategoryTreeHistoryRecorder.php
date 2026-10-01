<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistory\Model;

use Ergonode\CategoryConsumerHistory\Model\Context\HistoryExecutionContext;
use Ergonode\CategoryConsumerHistory\Model\ResourceModel\HistoryWriter;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use Throwable;

class CategoryTreeHistoryRecorder
{
    public function __construct(
        private readonly TreeDiffer $treeDiffer,
        private readonly HistoryWriter $historyWriter,
        private readonly HistoryExecutionContext $executionContext,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param array<string, int|string|bool|null> $operationSummary
     * @param array<int, array<string, mixed>> $beforeStates
     * @param array<int, array<string, mixed>> $afterStates
     */
    public function record(
        string $operationCode,
        string $status,
        string $startedAt,
        array $operationSummary,
        array $beforeStates,
        array $afterStates
    ): void {
        try {
            $changeSets = $this->changeSets($beforeStates, $afterStates);
            $this->historyWriter->save(
                $operationCode,
                $status,
                $startedAt,
                $this->executionContext->get(),
                $operationSummary,
                $changeSets
            );
        } catch (Throwable $exception) {
            $this->logger->error('Unable to persist Ergonode category tree history.', [
                'operation_code' => $operationCode,
                'exception' => $exception,
            ]);
        }
    }
    public function begin(string $code, string $startedAt): ?int
    {
        try {
            return $this->historyWriter->start($code, $startedAt, $this->executionContext->get());
        } catch (Throwable $exception) {
            $this->logger->error('Unable to start category history.', ['exception' => $exception]);
            return null;
        }
    }

    /** @param array<string, int|string|bool|null> $summary */
    public function finish(?int $operationId, string $status, array $summary): void
    {
        if ($operationId === null) {
            return;
        }
        try {
            $this->historyWriter->finish($operationId, $status, $summary);
        } catch (Throwable $exception) {
            $this->logger->error('Unable to finish category history.', ['exception' => $exception]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $before
     * @param array<int, array<string, mixed>> $after
     */
    public function append(?int $operationId, array $before, array $after): void
    {
        if ($operationId === null) {
            return;
        }
        try {
            $this->historyWriter->append($operationId, $this->changeSets($before, $after));
        } catch (Throwable $exception) {
            $this->logger->error('Unable to append category history.', ['exception' => $exception]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $beforeStates
     * @param array<int, array<string, mixed>> $afterStates
     * @return list<array<string, mixed>>
     */
    private function changeSets(array $beforeStates, array $afterStates): array
    {
        $changeSets = [];
        $treeIds = array_values(array_unique([...array_keys($beforeStates), ...array_keys($afterStates)]));
        sort($treeIds);
        foreach ($treeIds as $treeId) {
            $before = $beforeStates[$treeId] ?? null;
            $after = $afterStates[$treeId] ?? null;
            if (!is_array($before) || !is_array($after)) {
                continue;
            }
            $difference = $this->treeDiffer->diff($before, $after);
            $tree = $after['tree'] ?? $before['tree'] ?? null;
            if (!is_array($tree)) {
                continue;
            }
            $changeSets[] = [
                'tree' => $tree,
                'before_hash' => hash('sha256', $this->json->serialize($before)),
                'after_hash' => hash('sha256', $this->json->serialize($after)),
                'summary' => $difference['summary'],
                'changes' => $difference['changes'],
            ];
        }

        return $changeSets;
    }
}
