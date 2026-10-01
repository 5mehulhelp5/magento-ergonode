<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeAdminUi\Model\Mapping;

use Ergonode\CategoryAttribute\Api\MagentoOptionProviderInterface;
use Ergonode\CategoryAttribute\Api\MappingReaderInterface;
use Ergonode\CategoryAttribute\Api\OptionMappingWriterInterface;
use Magento\Framework\Exception\LocalizedException;

class OptionMappingSaver
{
    public function __construct(
        private readonly SourceMetadata $source,
        private readonly MagentoOptionProviderInterface $target,
        private readonly MappingReaderInterface $mappingReader,
        private readonly OptionMappingWriterInterface $writer,
        private readonly SaveContext $saveContext
    ) {
    }

    /** @param array<int, array<string, mixed>> $mappings
     * @param array<int, array<string, mixed>> $visibility
     * @return array<string, mixed>
     */
    public function save(int $mappingId, array $mappings, array $visibility): array
    {
        return $this->saveContext->execute(function () use ($mappingId, $mappings, $visibility): array {
            $mappings = $this->prepareMappings($mappingId, $mappings);
            $attribute = $this->mappingReader->getAttributeRow($mappingId);
            if (!$attribute || (string)($attribute['status'] ?? '') !== 'complete') {
                throw new LocalizedException(__('Options require a saved category attribute mapping.'));
            }
            $metadata = [
                'left' => array_column(
                    $this->source->getOptions((string)$attribute['ergonode_attribute_code']),
                    null,
                    'code'
                ),
                'right' => array_column(
                    $this->target->getOptions((string)$attribute['magento_attribute_code']),
                    null,
                    'code'
                ),
            ];
            $resolved = [];
            foreach ($mappings as $mapping) {
                $row = [];
                foreach ($metadata as $side => $options) {
                    $card = is_array($mapping[$side] ?? null) ? $mapping[$side] : [];
                    $code = trim((string)($card['code'] ?? ''));
                    if (!empty($card['pending_create'])) {
                        throw new LocalizedException(__('Resolve pending category options before saving.'));
                    }
                    if ($code !== '' && !isset($options[$code])) {
                        throw new LocalizedException(__(
                            'Category option "%1" is not available for mapping.',
                            $code
                        ));
                    }
                    $row[$side] = $code !== '' ? $options[$code] : null;
                }
                $resolved[] = $row;
            }
            return $this->writer->save($mappingId, $resolved, $visibility);
        });
    }

    /**
     * @param array<int, array<string, mixed>> $mappings
     * @return array<int, array<string, mixed>>
     */
    public function prepareMappings(int $mappingId, array $mappings): array
    {
        return $mappings;
    }
}
