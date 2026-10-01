<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeAdminUi\Model\Mapping;

use Ergonode\ProductAttribute\Api\MappingReaderInterface;
use Ergonode\ProductAttributeAdminUi\Api\ErgonodeMetadataProviderInterface;
use Ergonode\ProductAttribute\Model\Provider\MagentoOptionProviderFactory;
use Ergonode\ProductAttribute\Model\Mapping\OptionMappingSaver as MappingSaver;
use Magento\Framework\Exception\LocalizedException;

class OptionMappingSaver
{
    public function __construct(
        private readonly AttributeMappingProvider $attributeMappingProvider,
        private readonly ErgonodeMetadataProviderInterface $ergonodeProvider,
        private readonly MappingReaderInterface $mappingReader,
        private readonly MagentoOptionProviderFactory $magentoProviderFactory,
        private readonly MappingSaver $mappingSaver
    ) {
    }

    /**
     * @param  array<int, array<string, mixed>> $mappings
     * @param  array<int, array<string, mixed>> $visibility
     * @return array<string, int>
     */
    public function save(int $attributeMappingId, array $mappings, array $visibility): array
    {
        $context = $this->attributeMappingProvider->getMappingRow($attributeMappingId);
        if (!$context || $context['ergonode_attribute_code'] === '' || $context['magento_attribute_code'] === '') {
            throw new LocalizedException(__('Options require a saved attribute mapping.'));
        }
        $metadata = [
            'left' => array_column(
                $this->ergonodeProvider->getOptions($context['ergonode_attribute_code']),
                null,
                'code'
            ),
            'right' => array_column(
                $this->magentoProviderFactory->create()->getOptions($context['magento_attribute_code']),
                null,
                'code'
            ),
        ];
        $verified = $this->ergonodeProvider->getVerifiedOptions($context['ergonode_attribute_code']);
        $existing = [];
        foreach ($this->mappingReader->getOptionRows($attributeMappingId) as $row) {
            $code = trim((string)($row['ergonode_option_code'] ?? ''));
            if ($code !== '') {
                $existing[$code] = isset($row['magento_option_id'])
                    ? 'option_' . (string)$row['magento_option_id'] : '';
            }
        }
        foreach ($mappings as $mapping) {
            foreach ($metadata as $side => $options) {
                $card = $mapping[$side] ?? null;
                $code = is_array($card) ? trim((string)($card['code'] ?? '')) : '';
                if (!empty($card['pending_create']) || str_starts_with($code, 'pending_')) {
                    throw new LocalizedException(__('The module required to create this option is unavailable.'));
                }
                if ($code !== '' && !isset($options[$code])) {
                    throw new LocalizedException(__('Option "%1" is not available for mapping.', $code));
                }
                if ($side === 'left' && $code !== '' && !isset($verified[$code])
                    && (!array_key_exists($code, $existing)
                        || $existing[$code] !== trim((string)($mapping['right']['code'] ?? '')))
                ) {
                    throw new LocalizedException(__('Option "%1" must be verified before mapping.', $code));
                }
            }
        }

        return $this->mappingSaver->save($attributeMappingId, $mappings, $visibility);
    }
}
