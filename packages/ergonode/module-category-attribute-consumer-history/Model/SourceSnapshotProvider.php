<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumerHistory\Model;

use Ergonode\AttributeConsumer\Api\ErgonodeAttributeProviderInterfaceFactory;
use Ergonode\CategoryAttributeConsumer\Model\Provider\ErgonodeCategoryAttributeProviderFactory;
use Ergonode\CategoryAttributeHistory\Api\SourceSnapshotProviderInterface;

class SourceSnapshotProvider implements SourceSnapshotProviderInterface
{
    public function __construct(
        private readonly ErgonodeCategoryAttributeProviderFactory $categoryProviderFactory,
        private readonly ErgonodeAttributeProviderInterfaceFactory $attributeProviderFactory
    ) {
    }

    public function getAttributeMap(): array
    {
        $provider = $this->categoryProviderFactory->create([
            'attributeProvider' => $this->attributeProviderFactory->create(),
        ]);

        return $provider->getAttributeMap();
    }
}
