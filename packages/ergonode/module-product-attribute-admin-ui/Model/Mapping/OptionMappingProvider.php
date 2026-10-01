<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeAdminUi\Model\Mapping;

use Ergonode\ProductAttribute\Api\MappingReaderInterface;
use Ergonode\ProductAttribute\Model\Mapping\MappingStateBuilder;
use Ergonode\ProductAttributeAdminUi\Api\ErgonodeMetadataProviderInterface;
use Ergonode\ProductAttribute\Model\Provider\MagentoOptionProvider;
use Ergonode\ProductAttributeAdminUi\Model\Mapping\AttributeMappingProvider;

class OptionMappingProvider
{
    public function __construct(
        private readonly MappingReaderInterface $mappingReader,
        private readonly MappingStateBuilder $stateBuilder,
        private readonly AttributeMappingProvider $attributeMappingProvider,
        private readonly ErgonodeMetadataProviderInterface $ergonodeOptionProvider,
        private readonly MagentoOptionProvider $magentoOptionProvider
    ) {
    }

    /**
     * @return array{context: array<string, mixed>|null, contexts: array<int, array<string, mixed>>}
     */
    public function getContext(?int $requestedMappingId = null): array
    {
        $contexts = $this->attributeMappingProvider->getOptionAttributeContexts();

        if (!$contexts) {
            return ['context' => null, 'contexts' => []];
        }

        $selected = $contexts[0];
        foreach ($contexts as $context) {
            if ((int)$context['mapping_id'] === $requestedMappingId) {
                $selected = $context;
                break;
            }
        }

        return ['context' => $selected, 'contexts' => $contexts];
    }

    /**
     * @return array<int, array{left: array<string, string>|null, right: array<string, string>|null, tone?: string}>
     */
    public function getMappings(
        int $attributeMappingId,
        string $ergonodeAttributeCode,
        string $magentoAttributeCode
    ): array {
        return $this->stateBuilder->options(
            $this->mappingReader->getOptionRows($attributeMappingId),
            $this->ergonodeOptionProvider->getOptions($ergonodeAttributeCode),
            $this->magentoOptionProvider->getOptions($magentoAttributeCode)
        );
    }
}
