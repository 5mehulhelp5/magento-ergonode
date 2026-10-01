<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\Mapping;

use Ergonode\AttributeConsumer\Api\ErgonodeOptionProviderInterface;
use Ergonode\CategoryAttribute\Api\MagentoOptionProviderInterface;
use Ergonode\CategoryAttribute\Api\MappingReaderInterface;
use Ergonode\CategoryAttribute\Api\MappingStateBuilderInterface;

class CategoryOptionMappingProvider
{
    public function __construct(
        private readonly MappingReaderInterface $mappingReader,
        private readonly MappingStateBuilderInterface $stateBuilder,
        private readonly CategoryAttributeMappingProvider $attributeMappingProvider,
        private readonly ErgonodeOptionProviderInterface $ergonodeOptionProvider,
        private readonly MagentoOptionProviderInterface $magentoOptionProvider
    ) {
    }

    /** @return array{context: array<string, mixed>|null, contexts: array<int, array<string, mixed>>} */
    public function getContext(?int $requestedMappingId = null): array
    {
        $contexts = $this->attributeMappingProvider->getOptionAttributeContexts();
        if ($contexts === []) {
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

    /** @return array<int, array<string, mixed>> */
    public function getMappings(int $mappingId, string $ergonodeCode, string $magentoCode): array
    {
        return $this->stateBuilder->options(
            $this->mappingReader->getOptionRows($mappingId),
            $this->ergonodeOptionProvider->getOptions($ergonodeCode),
            $this->magentoOptionProvider->getOptions($magentoCode)
        );
    }
}
