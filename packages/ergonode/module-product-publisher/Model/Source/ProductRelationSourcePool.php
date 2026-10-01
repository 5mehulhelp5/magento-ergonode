<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\Source;

use Ergonode\ProductPublisher\Api\Data\ProductRelationSourceResultInterface;
use Ergonode\ProductPublisher\Api\ProductDesiredStateFactoryInterface;
use Ergonode\ProductPublisher\Api\ProductRelationSourceInterface;
use Ergonode\ProductPublisher\Api\ProductRelationSourcePoolInterface;
use Ergonode\ProductPublisher\Api\ProductRelationSourceResultFactoryInterface;
use InvalidArgumentException;
use Magento\Catalog\Model\Product;

class ProductRelationSourcePool implements ProductRelationSourcePoolInterface
{
    /** @var array<string, ProductRelationSourceInterface> */
    private array $sources;

    /** @param ProductRelationSourceInterface[] $sources */
    public function __construct(
        private readonly ProductDesiredStateFactoryInterface $stateFactory,
        private readonly ProductRelationSourceResultFactoryInterface $resultFactory,
        array $sources = []
    ) {
        $this->sources = [];
        foreach ($sources as $source) {
            if (!$source instanceof ProductRelationSourceInterface) {
                throw new InvalidArgumentException('Product relation source pool accepts only source contracts.');
            }
            $typeId = trim($source->getMagentoTypeId());
            if ($typeId === '' || isset($this->sources[$typeId])) {
                throw new InvalidArgumentException('Product relation source type IDs must be non-empty and unique.');
            }
            $this->sources[$typeId] = $source;
        }
    }

    public function extract(Product $product, array $targetCodesByMagentoCode): ProductRelationSourceResultInterface
    {
        $source = $this->sources[(string)$product->getTypeId()] ?? null;
        if ($source !== null) {
            return $source->extract($product, $targetCodesByMagentoCode);
        }

        return $this->resultFactory->create($this->stateFactory->createRelations());
    }
}
