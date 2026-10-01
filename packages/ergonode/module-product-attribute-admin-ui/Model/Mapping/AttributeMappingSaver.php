<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeAdminUi\Model\Mapping;

use Ergonode\ProductAttribute\Api\MappingReaderInterface;
use Ergonode\ProductAttributeAdminUi\Api\ErgonodeMetadataProviderInterface;
use Ergonode\ProductAttribute\Model\Provider\MagentoAttributeProviderFactory;
use Ergonode\ProductAttribute\Model\Mapping\AttributeMappingSaver as MappingSaver;
use Magento\Framework\Exception\LocalizedException;

class AttributeMappingSaver
{
    public function __construct(
        private readonly ErgonodeMetadataProviderInterface $ergonodeProvider,
        private readonly MappingReaderInterface $mappingReader,
        private readonly MagentoAttributeProviderFactory $magentoProviderFactory,
        private readonly MappingSaver $mappingSaver
    ) {
    }

    /**
     * @param  array<int, array<string, mixed>> $mappings
     * @param  array<int, array<string, mixed>> $visibility
     * @return array{inserted: int, updated: int, deleted: int, unchanged: int}
     */
    public function save(array $mappings, array $visibility): array
    {
        $ergonode = $this->ergonodeProvider->getAttributeMap();
        $verified = $this->ergonodeProvider->getVerifiedAttributeMap();
        $magento = $this->magentoProviderFactory->create()->getAttributeMap();
        $existing = [];
        foreach ($this->mappingReader->getAttributeRows() as $row) {
            $code = trim((string)($row['ergonode_attribute_code'] ?? ''));
            if ($code !== '') {
                $existing[$code] = trim((string)($row['magento_attribute_code'] ?? ''));
            }
        }
        $resolved = [];
        foreach ($mappings as $mapping) {
            $row = [];
            foreach (['left' => $ergonode, 'right' => $magento] as $side => $metadata) {
                $card = $mapping[$side] ?? null;
                $code = is_array($card) ? trim((string)($card['code'] ?? '')) : '';
                if (!empty($card['pending_create'])) {
                    throw new LocalizedException(__('The module required to create this attribute is unavailable.'));
                }
                if ($code !== '' && !isset($metadata[$code])) {
                    throw new LocalizedException(__('Attribute "%1" is not available for mapping.', $code));
                }
                if ($side === 'left' && $code !== '' && !isset($verified[$code])
                    && (!array_key_exists($code, $existing)
                        || $existing[$code] !== trim((string)($mapping['right']['code'] ?? '')))
                ) {
                    throw new LocalizedException(__('Attribute "%1" must be verified before mapping.', $code));
                }
                $row[$side] = $code !== '' ? $metadata[$code] : null;
            }
            $resolved[] = $row;
        }

        return $this->mappingSaver->save($resolved, $visibility);
    }
}
