<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeAdminUi\Model\Mapping;

use Ergonode\CategoryAttribute\Api\AttributeMappingWriterInterface;
use Ergonode\CategoryAttribute\Api\MagentoAttributeProviderInterface;
use Magento\Framework\Exception\LocalizedException;

class AttributeMappingSaver
{
    public function __construct(
        private readonly SourceMetadata $source,
        private readonly MagentoAttributeProviderInterface $target,
        private readonly AttributeMappingWriterInterface $writer,
        private readonly SaveContext $saveContext
    ) {
    }

    /** @param array<int, array<string, mixed>> $mappings
     * @param array<int, array<string, mixed>> $visibility
     * @return array<string, mixed>
     */
    public function save(array $mappings, array $visibility): array
    {
        return $this->saveContext->execute(function () use ($mappings, $visibility): array {
            $mappings = $this->prepareMappings($mappings);
            $metadata = [
                'left' => array_column($this->source->getAttributes(), null, 'code'),
                'right' => $this->target->getAttributeMap(),
            ];
            $resolved = [];
            foreach ($mappings as $mapping) {
                $row = [];
                foreach ($metadata as $side => $attributes) {
                    $card = is_array($mapping[$side] ?? null) ? $mapping[$side] : [];
                    $code = trim((string)($card['code'] ?? ''));
                    if (!empty($card['pending_create'])) {
                        throw new LocalizedException(__('Resolve pending category attributes before saving.'));
                    }
                    if ($code !== '' && !isset($attributes[$code])) {
                        throw new LocalizedException(__(
                            'Category attribute "%1" is not available for mapping.',
                            $code
                        ));
                    }
                    $row[$side] = $code !== '' ? $attributes[$code] : null;
                }
                $resolved[] = $row;
            }
            return $this->writer->save($resolved, $visibility);
        });
    }

    /**
     * @param array<int, array<string, mixed>> $mappings
     * @return array<int, array<string, mixed>>
     */
    public function prepareMappings(array $mappings): array
    {
        return $mappings;
    }
}
