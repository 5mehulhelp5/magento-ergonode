<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumerAdminUi\Model\Mapping;

use Ergonode\AttributeConsumer\Model\Provider\ErgonodeAttributeProvider;
use Ergonode\AttributeConsumer\Model\Provider\ErgonodeOptionProvider;
use Ergonode\ProductAttributeAdminUi\Api\ErgonodeMetadataSourceInterface;

class SnapshotMetadataSource implements ErgonodeMetadataSourceInterface
{
    public function __construct(
        private readonly ErgonodeAttributeProvider $attributeProvider,
        private readonly ErgonodeOptionProvider $optionProvider
    ) {
    }

    public function getAttributes(): array
    {
        return $this->attributeProvider->getAttributes();
    }

    public function getOptions(string $attributeCode): array
    {
        return $this->optionProvider->getOptions($attributeCode);
    }

    public function getOptionCounts(array $attributeCodes): array
    {
        return $this->optionProvider->getOptionCounts($attributeCodes);
    }
}
