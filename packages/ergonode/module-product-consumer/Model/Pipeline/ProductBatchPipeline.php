<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Pipeline;

use Ergonode\ProductConsumer\Api\BatchProcessorInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Throwable;

class ProductBatchPipeline
{
    /** @param array<string, BatchProcessorInterface> $preprocessors
     * @param array<string, BatchProcessorInterface> $processors
     * @param array<string, BatchProcessorInterface> $postprocessors */
    public function __construct(
        private readonly BatchScope $scope,
        private readonly FinishBatch $finish,
        private readonly LoggerInterface $logger,
        private readonly array $preprocessors = [],
        private readonly array $processors = [],
        private readonly array $postprocessors = [],
        private readonly ?\Ergonode\Product\Model\Synchronization\ProductSynchronizationLock $lock = null
    ) {
        foreach ([$preprocessors, $processors, $postprocessors] as $phase) {
            foreach ($phase as $processor) {
                if (!$processor instanceof BatchProcessorInterface) {
                    throw new InvalidArgumentException('Invalid Ergonode batch processor.');
                }
            }
        }
    }

    /** @param list<BatchEntry> $entries */
    public function run(array $entries): BatchContext
    {
        $context = new BatchContext($entries, $this->logger);
        $execute = function () use ($context): void {
            $this->scope->run($context, function () use ($context): void {
                foreach (['preprocess' => $this->preprocessors, 'process' => $this->processors,
                    'postprocess' => $this->postprocessors] as $phase => $processors) {
                    ksort($processors, SORT_STRING);
                    foreach ($processors as $name => $processor) {
                        try {
                            $processor->process($context);
                        } catch (Throwable $error) {
                            // A batch-level failure blocks later writes, while keeping one diagnostic per item.
                            $context->failBatch($phase . ':' . $name, $error);
                        }
                    }
                }
                foreach ($context->entries as $entry) {
                    $context->run($entry, 'postprocess:import-checkpoint', static function () use ($entry): void {
                        foreach ($entry->completion as $complete) { $complete(); }
                    });
                }
                // Finalization cannot be moved before an extension postprocessor by its sort order.
                $this->finish->process($context);
            });
        };
        if ($this->lock !== null) { $this->lock->run($execute); } else { $execute(); }
        return $context;
    }
}
