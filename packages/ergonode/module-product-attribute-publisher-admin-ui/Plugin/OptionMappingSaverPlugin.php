<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisherAdminUi\Plugin;

use Ergonode\ProductAttribute\Api\MappingSaveNoticeCollectorInterface;
use Ergonode\ProductAttribute\Api\MappingReaderInterface;
use Ergonode\ProductAttributeAdminUi\Api\ErgonodeMetadataProviderInterface;
use Magento\Framework\Exception\LocalizedException;
use Ergonode\ProductAttributeAdminUi\Model\Mapping\AttributeMappingProvider;
use Ergonode\ProductAttributeAdminUi\Model\Mapping\OptionMappingSaver;
use Ergonode\ProductAttributePublisherAdminUi\Model\ErgonodeOptionCreator;
use Ergonode\ProductAttributePublisherAdminUi\Model\PublishedMetadataVerifier;
use Throwable;

class OptionMappingSaverPlugin
{
    public function __construct(
        private readonly AttributeMappingProvider $attributeMappingProvider,
        private readonly ErgonodeOptionCreator $optionCreator,
        private readonly MappingSaveNoticeCollectorInterface $noticeCollector,
        private readonly MappingReaderInterface $mappingReader,
        private readonly ErgonodeMetadataProviderInterface $metadataProvider,
        private readonly PublishedMetadataVerifier $metadataVerifier
    ) {
    }

    /**
     * @param  callable                         $proceed
     * @param  array<int, array<string, mixed>> $mappings
     * @param  array<int, array<string, mixed>> $visibility
     * @return array<string, int>
     * @throws LocalizedException
     */
    public function aroundSave(
        OptionMappingSaver $subject,
        callable $proceed,
        int $attributeMappingId,
        array $mappings,
        array $visibility
    ): array {
        $context = $this->attributeMappingProvider->getMappingRow($attributeMappingId);
        $attributeCode = is_array($context)
            ? trim((string)($context['ergonode_attribute_code'] ?? ''))
            : '';

        $magentoCode = trim((string)($context['magento_attribute_code'] ?? ''));
        $pendingSources = [];
        $generatedCodes = [];
        foreach ($mappings as &$mapping) {
            $left = isset($mapping['left']) && is_array($mapping['left']) ? $mapping['left'] : null;
            $right = isset($mapping['right']) && is_array($mapping['right']) ? $mapping['right'] : null;
            if ($right && $left && !empty($left['pending_create'])) {
                if ($attributeCode === '' || $magentoCode === '') {
                    throw new LocalizedException(__('Missing Ergonode attribute mapping context.'));
                }
                $state = $this->optionCreator->prepareState($magentoCode, $attributeCode, $right);
                $mapping['left'] = $this->optionCreator->prepareMapping($state);
                $generatedCodes[] = (string)($mapping['left']['code'] ?? '');
                $pendingSources[] = ['state' => $state, 'code' => (string)($right['code'] ?? '')];
            }
        }
        unset($mapping);
        $this->validateUniqueGeneratedCodes($mappings, $generatedCodes);

        try {
            foreach ($pendingSources as $source) {
                $warning = $this->optionCreator->synchronizeFromMagento(
                    $attributeCode,
                    $source['state'],
                    $source['code']
                );
                if ($warning !== null) {
                    $this->noticeCollector->addWarning($warning);
                }
            }
        } catch (Throwable $exception) {
            if ($exception instanceof LocalizedException) {
                throw $exception;
            }

            throw new LocalizedException(
                __('Unable to synchronize the Ergonode option before saving its mapping.'),
                $exception
            );
        }

        $this->verifyNewMappings($attributeMappingId, $attributeCode, $mappings);

        return $proceed($attributeMappingId, $mappings, $visibility);
    }

    /** @param array<int, array<string, mixed>> $mappings */
    private function verifyNewMappings(int $mappingId, string $attributeCode, array $mappings): void
    {
        $existing = [];
        foreach ($this->mappingReader->getOptionRows($mappingId) as $row) {
            $code = trim((string)($row['ergonode_option_code'] ?? ''));
            if ($code !== '') {
                $existing[$code] = isset($row['magento_option_id'])
                    ? 'option_' . (string)$row['magento_option_id'] : '';
            }
        }
        $verified = $this->metadataProvider->getVerifiedOptions($attributeCode);
        foreach ($mappings as $mapping) {
            $code = trim((string)($mapping['left']['code'] ?? ''));
            $magentoCode = trim((string)($mapping['right']['code'] ?? ''));
            if ($code !== '' && !isset($verified[$code]) && ($existing[$code] ?? null) !== $magentoCode) {
                $this->metadataVerifier->verifyAttributes([$attributeCode]);
                return;
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>> $mappings
     * @param  string[]                         $generatedCodes
     * @throws LocalizedException
     */
    private function validateUniqueGeneratedCodes(array $mappings, array $generatedCodes): void
    {
        $generatedCodes = array_fill_keys(array_filter($generatedCodes), true);
        $occurrences = [];
        foreach ($mappings as $mapping) {
            $left = isset($mapping['left']) && is_array($mapping['left']) ? $mapping['left'] : null;
            $code = trim((string)($left['code'] ?? ''));
            if (!isset($generatedCodes[$code])) {
                continue;
            }
            $occurrences[$code] = ($occurrences[$code] ?? 0) + 1;
            if ($occurrences[$code] > 1) {
                throw new LocalizedException(
                    __('Ergonode option code "%1" is generated for more than one Magento option.', $code)
                );
            }
        }
    }
}
