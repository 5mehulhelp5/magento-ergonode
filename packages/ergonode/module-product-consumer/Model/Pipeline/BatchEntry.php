<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Pipeline;

use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Ergonode\ProductConsumer\Model\Data\ProductImportWorkItem;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;
use Throwable;

class BatchEntry
{
    public ?RemoteProduct $source = null;
    public ?int $productId = null;
    public ?Throwable $error = null;
    public ?string $failedStage = null;
    public ?string $logReference = null;
    public bool $changed = false;
    /** @var list<\Closure> Commit import checkpoints only after every processor succeeds. */
    public array $completion = [];

    public function __construct(
        public readonly ProductImportWorkItem|ProductIdentityInterface|null $request,
        ?int $missingProductId = null
    ) {
        $this->productId = $missingProductId;
        if ($request instanceof ProductIdentityInterface) {
            $this->productId = $request->getProductId();
        }
    }

    public function sku(): string
    {
        return $this->request instanceof ProductImportWorkItem
            ? $this->request->sku : ($this->request?->getErgonodeSku() ?? (string)$this->productId);
    }
}
