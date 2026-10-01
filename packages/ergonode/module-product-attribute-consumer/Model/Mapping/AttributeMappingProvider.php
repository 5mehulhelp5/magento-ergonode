<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Model\Mapping;

use Ergonode\ProductAttribute\Api\MappingReaderInterface;
use Ergonode\ProductAttribute\Model\Mapping\MappingStateBuilder;
use Ergonode\AttributeConsumer\Api\ErgonodeOptionProviderInterface;
use Ergonode\AttributeConsumer\Model\Provider\ErgonodeAttributeProvider;
use Ergonode\ProductAttribute\Model\Provider\MagentoAttributeProvider;

class AttributeMappingProvider
{
    /**
     * @var array<int, array{
     *     mapping_id: int,
     *     left: array{label: string, code: string, type: string, scope: string, active?: bool}|null,
     *     right: array{
     *         label: string,
     *         code: string,
     *         type: string,
     *         scope: string,
     *         active?: bool,
     *         required?: bool,
     *         has_custom_source?: bool
     *     }|null,
     *     tone?: string
     * }>|null
     */
    private ?array $mappingsCache = null;

    public function __construct(
        private readonly MappingReaderInterface $mappingReader,
        private readonly ErgonodeAttributeProvider $ergonodeAttributeProvider,
        private readonly MagentoAttributeProvider $magentoAttributeProvider,
        private readonly MappingStateBuilder $stateBuilder,
        private readonly ErgonodeOptionProviderInterface $ergonodeOptionProvider
    ) {
    }

    /**
     * @return array<int, array{
     *     mapping_id: int,
     *     left: array{label: string, code: string, type: string, scope: string, active?: bool}|null,
     *     right: array{
     *         label: string,
     *         code: string,
     *         type: string,
     *         scope: string,
     *         active?: bool,
     *         required?: bool,
     *         has_custom_source?: bool
     *     }|null,
     *     tone?: string
     * }>
     */
    public function getMappings(): array
    {
        return $this->mappingsCache ??= $this->stateBuilder->attributes(
            $this->mappingReader->getAttributeRows(),
            $this->ergonodeAttributeProvider->getAttributeMap(),
            $this->magentoAttributeProvider->getAttributeMap()
        );
    }

    public function clearCache(): void
    {
        $this->mappingsCache = null;
    }

    /**
     * @return array<int, array{mapped: int, total: int}>
     */
    public function getOptionMappingProgress(): array
    {
        $contexts = $this->getOptionAttributeContexts();
        $optionTotals = $this->ergonodeOptionProvider->getOptionCounts(
            array_column(array_column($contexts, 'left'), 'code')
        );
        $mappedTotals = $this->mappingReader->getCompleteOptionCounts(array_column($contexts, 'mapping_id'));
        $progress = [];
        foreach ($contexts as $context) {
            $mappingId = (int)$context['mapping_id'];
            $progress[$mappingId] = [
                'mapped' => (int)($mappedTotals[$mappingId] ?? 0),
                'total' => (int)($optionTotals[$context['left']['code']] ?? 0),
            ];
        }

        return $progress;
    }

    /**
     * @return array<int, array{
     *     code: string,
     *     mapping_id: int,
     *     left: array{label: string, code: string, type: string, scope: string, active?: bool},
     *     right: array{
     *         label: string,
     *         code: string,
     *         type: string,
     *         scope: string,
     *         active?: bool,
     *         required?: bool,
     *         has_custom_source?: bool
     *     }
     * }>
     */
    public function getOptionAttributeContexts(): array
    {
        return $this->stateBuilder->optionContexts($this->getMappings());
    }

    /**
     * @return array{
     *     mapping_id: int,
     *     ergonode_attribute_code: string,
     *     magento_attribute_code: string,
     *     ergonode_type: string,
     *     magento_type: string,
     *     magento_has_custom_source: bool
     * }|null
     */
    public function getMappingRow(int $mappingId): ?array
    {
        $row = $this->mappingReader->getAttributeRow($mappingId);
        if ($row === null) {
            return null;
        }

        return $this->stateBuilder->context(
            $row,
            $this->ergonodeAttributeProvider->getAttribute((string)($row['ergonode_attribute_code'] ?? '')),
            $this->magentoAttributeProvider->getAttribute((string)($row['magento_attribute_code'] ?? ''))
        );
    }
}
