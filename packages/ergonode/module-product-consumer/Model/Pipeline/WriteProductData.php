<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Pipeline;

use Ergonode\ProductConsumer\Api\BatchProcessorInterface;
use Ergonode\ProductConsumer\Model\Data\ProductImportWorkItem;
use Ergonode\ProductConsumer\Model\Import\SelectedProductImporter;
use Ergonode\ProductConsumer\Model\Queue\ProductImportProcessor;

class WriteProductData implements BatchProcessorInterface
{
    public function __construct(
        private readonly ProductImportProcessor $products,
        private readonly SelectedProductImporter $selected
    ) {
    }

    public function process(BatchContext $context): void
    {
        foreach ($context->entries as $entry) {
            $context->run($entry, 'process:attributes', function () use ($entry): void {
                if ($entry->request instanceof ProductImportWorkItem) {
                    $this->products->processSource($entry->request, $entry->source);
                } else {
                    $this->selected->importSource($entry->request, $entry->source);
                }
            });
        }
    }
}
