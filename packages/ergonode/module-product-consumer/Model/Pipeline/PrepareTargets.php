<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Pipeline;

use Ergonode\ProductConsumer\Api\BatchProcessorInterface;
use Ergonode\ProductConsumer\Model\Magento\ProductTargetResolver;
use Ergonode\ProductConsumer\Model\ResourceModel\BatchProductTargets;
use Ergonode\Product\Model\Cache\ProductCacheFinalizer;

class PrepareTargets implements BatchProcessorInterface
{
    public function __construct(
        private readonly BatchProductTargets $targets,
        private readonly ProductTargetResolver $resolver,
        private readonly ProductCacheFinalizer $cache
    ) {
    }

    public function process(BatchContext $context): void
    {
        $targets = $this->targets->get($context);
        $this->resolver->setBatchTargets($targets);
        $context->data['cleanup'][] = fn() => $this->resolver->setBatchTargets(null);
        $context->data['targets'] = $targets;
        $context->before = $this->cache->begin($context->productIds());
    }
}
