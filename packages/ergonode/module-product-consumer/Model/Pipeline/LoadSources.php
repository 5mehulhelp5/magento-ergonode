<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Pipeline;

use Ergonode\ProductConsumer\Api\BatchProcessorInterface;
use Ergonode\ProductConsumer\Model\Data\ProductImportWorkItem;
use Ergonode\ProductConsumer\Model\GraphQl\RemoteProductLoader;
use Magento\Framework\Exception\LocalizedException;

class LoadSources implements BatchProcessorInterface
{
    public function __construct(private readonly RemoteProductLoader $loader)
    {
    }

    public function process(BatchContext $context): void
    {
        foreach ($context->entries as $entry) {
            $context->run($entry, 'preprocess:source', function () use ($entry): void {
                if ($entry->request === null) {
                    throw new LocalizedException(__('This product has no Ergonode SKU mapping.'));
                }
                $entry->source = $entry->request instanceof ProductImportWorkItem
                    ? $this->loader->loadCurrent($entry->sku(),
                        $entry->request->operation === ProductImportWorkItem::OPERATION_DELETE ? null : $entry->request->payload)
                    : $this->loader->load($entry->sku());
                if ($entry->source === null && !$entry->request instanceof ProductImportWorkItem) {
                    throw new LocalizedException(__('The mapped product does not exist in Ergonode.'));
                }
            });
        }
    }
}
