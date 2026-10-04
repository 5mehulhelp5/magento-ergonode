<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Pipeline;

use Ergonode\Product\Model\Cache\ProductCacheFinalizer;
use Ergonode\ProductConsumer\Model\Magento\ProductIndexInvalidator;
use Throwable;

class FinishBatch
{
    public function __construct(private readonly ProductCacheFinalizer $cache, private readonly ProductIndexInvalidator $indexes)
    {
    }

    public function process(BatchContext $context): void
    {
        try {
            $changed = $this->cache->changes($context->productIds(), $context->before);
            foreach ($context->entries as $entry) { $entry->changed = isset($changed[$entry->productId]); }
            // Committed ordinary attributes may need indexing even if a subsequent stage failed.
            if ($changed !== []) { $this->indexes->invalidate(); }
            $completed = [];
            foreach ($context->entries as $entry) {
                if ($entry->error === null && $entry->changed) {
                    $completed[$entry->productId] = $changed[$entry->productId];
                }
            }
        } catch (Throwable $error) {
            $context->failBatch('postprocess:cache', $error);
            return;
        }
        try {
            $this->cache->complete($completed);
        } catch (Throwable $error) {
            $context->reportBatchError('postprocess:cache', $error, array_keys($completed));
        }
    }
}
