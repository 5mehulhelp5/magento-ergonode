<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeAdminUi\Model\Mapping;

use Ergonode\ProductAttribute\Api\MappingReaderInterface;
use Ergonode\ProductAttribute\Model\Mapping\MappingStateBuilder;
use Ergonode\ProductAttributeAdminUi\Api\ErgonodeMetadataProviderInterface;
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
        private readonly ErgonodeMetadataProviderInterface $ergonodeAttributeProvider,
        private readonly MagentoAttributeProvider $magentoAttributeProvider,
        private readonly MappingStateBuilder $stateBuilder,
        private readonly ErgonodeMetadataProviderInterface $ergonodeOptionProvider
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
        if ($this->mappingsCache !== null) {
            return $this->mappingsCache;
        }
        $mappings = $this->stateBuilder->attributes(
            $this->mappingReader->getAttributeRows(),
            $this->ergonodeAttributeProvider->getAttributeMap(),
            $this->magentoAttributeProvider->getAttributeMap()
        );
        foreach ($mappings as &$mapping) {
            if (empty($mapping['value_adapter'])) {
                continue;
            }
            $availability = $mapping['adapter_availability'];
            $mapping['validation_message'] = (string)__(
                'Adapter: %1. Import: %2. Publication: %3.',
                $mapping['value_adapter'],
                $availability['import'] ? __('available') : __('unavailable — attribute skipped'),
                $availability['publish'] ? __('available') : __('unavailable — attribute skipped')
            );
            $mapping['validation_tone'] = $availability['import'] && $availability['publish'] ? 'ok' : 'error';
        }
        unset($mapping);

        return $this->mappingsCache = $mappings;
    }

    /**
     * @return array<string, array{message: string, tone: string}>
     */
    public function getValidationMessages(): array
    {
        $messages = [];
        foreach ($this->getMappings() as $mapping) {
            $code = (string)($mapping['right']['code'] ?? '');
            if ($code !== '') {
                $messages[$code] = [
                    'message' => (string)($mapping['validation_message'] ?? ''),
                    'tone' => (string)($mapping['validation_tone'] ?? ''),
                ];
            }
        }

        return $messages;
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
