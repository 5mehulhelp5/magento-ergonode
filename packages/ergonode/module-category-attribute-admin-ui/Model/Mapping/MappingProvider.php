<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeAdminUi\Model\Mapping;

use Ergonode\Attribute\Api\AttributeTypeCompatibilityInterface;
use Ergonode\CategoryAttribute\Api\MagentoAttributeProviderInterface;
use Ergonode\CategoryAttribute\Api\MagentoOptionProviderInterface;
use Ergonode\CategoryAttribute\Api\MappingReaderInterface;
use Ergonode\CategoryAttribute\Api\MappingStateBuilderInterface;

class MappingProvider
{
    public function __construct(
        private readonly SourceMetadata $source,
        private readonly MagentoAttributeProviderInterface $attributes,
        private readonly MagentoOptionProviderInterface $options,
        private readonly MappingReaderInterface $reader,
        private readonly MappingStateBuilderInterface $stateBuilder,
        private readonly AttributeTypeCompatibilityInterface $compatibility
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function getErgonodeAttributes(): array
    {
        return $this->source->getAttributes();
    }

    /** @return array<int, array<string, mixed>> */
    public function getMagentoAttributes(): array
    {
        return $this->attributes->getAttributes();
    }

    /** @return array<int, array<string, mixed>> */
    public function getAttributeMappings(): array
    {
        return $this->stateBuilder->attributes(
            $this->reader->getAttributeRows(),
            array_column($this->getErgonodeAttributes(), null, 'code'),
            $this->attributes->getAttributeMap()
        );
    }

    /** @return array<int, array{mapped: int, total: int}> */
    public function getOptionMappingProgress(): array
    {
        $contexts = $this->getOptionContext(null)['contexts'];
        $counts = $this->reader->getCompleteOptionCounts(array_column($contexts, 'mapping_id'));
        $result = [];
        foreach ($contexts as $context) {
            $id = (int)$context['mapping_id'];
            $result[$id] = [
                'mapped' => (int)($counts[$id] ?? 0),
                'total' => count($this->getErgonodeOptions((string)$context['left']['code'])),
            ];
        }
        return $result;
    }

    /** @return array{context: array<string, mixed>|null, contexts: array<int, array<string, mixed>>} */
    public function getOptionContext(?int $mappingId): array
    {
        $contexts = [];
        $selected = null;
        foreach ($this->getAttributeMappings() as $mapping) {
            if (!$this->compatibility->canMapOptions(
                (string)($mapping['left']['type'] ?? ''),
                (string)($mapping['right']['type'] ?? '')
            )) {
                continue;
            }
            $mapping['code'] = (string)$mapping['mapping_id'];
            $contexts[] = $mapping;
            if ((int)$mapping['mapping_id'] === $mappingId) {
                $selected = $mapping;
            }
        }
        return ['context' => $selected ?? ($contexts[0] ?? null), 'contexts' => $contexts];
    }

    /** @return array<int, array<string, mixed>> */
    public function getOptionMappings(int $mappingId, string $ergonodeCode, string $magentoCode): array
    {
        return $this->stateBuilder->options(
            $this->reader->getOptionRows($mappingId),
            $this->getErgonodeOptions($ergonodeCode),
            $this->getMagentoOptions($magentoCode)
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function getErgonodeOptions(string $code): array
    {
        return $this->source->getOptions($code);
    }

    /** @return array<int, array<string, mixed>> */
    public function getMagentoOptions(string $code): array
    {
        return $this->options->getOptions($code);
    }
}
