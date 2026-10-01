<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisherAdminUi\Plugin;

use Ergonode\ProductAttribute\Api\MappingSaveNoticeCollectorInterface;
use Ergonode\ProductAttribute\Api\MappingReaderInterface;
use Ergonode\ProductAttributeAdminUi\Api\ErgonodeMetadataProviderInterface;
use Ergonode\ProductAttributeAdminUi\Model\Mapping\AttributeMappingSaver;
use Ergonode\ProductAttributePublisherAdminUi\Model\ErgonodeAttributeCreator;
use Ergonode\ProductAttributePublisherAdminUi\Model\PublishedMetadataVerifier;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class AttributeMappingSaverPlugin
{
    public function __construct(
        private readonly ErgonodeAttributeCreator $attributeCreator,
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
     */
    public function aroundSave(
        AttributeMappingSaver $subject,
        callable $proceed,
        array $mappings,
        array $visibility
    ): array {
        $pendingSources = [];
        foreach ($mappings as &$mapping) {
            $left = isset($mapping['left']) && is_array($mapping['left']) ? $mapping['left'] : null;
            $right = isset($mapping['right']) && is_array($mapping['right']) ? $mapping['right'] : null;
            if ($right && $left && !empty($left['pending_create'])) {
                $source = $right;
                if (!empty($left['type'])) {
                    $source['target_type'] = $left['type'];
                }
                $mapping['left'] = $this->attributeCreator->prepareMapping($source);
                $mapping['left']['pending_create'] = false;
                $pendingSources[] = $source;
            }
        }
        unset($mapping);

        try {
            foreach ($pendingSources as $source) {
                $warning = $this->attributeCreator->synchronizeFromMagento($source);
                if ($warning !== null) {
                    $this->noticeCollector->addWarning($warning);
                }
            }
        } catch (Throwable $exception) {
            if ($exception instanceof LocalizedException) {
                throw $exception;
            }

            throw new LocalizedException(
                __('Unable to synchronize the Ergonode attribute before saving its mapping.'),
                $exception
            );
        }

        $this->verifyNewMappings($mappings);

        return $proceed($mappings, $visibility);
    }

    /** @param array<int, array<string, mixed>> $mappings */
    private function verifyNewMappings(array $mappings): void
    {
        $existing = [];
        foreach ($this->mappingReader->getAttributeRows() as $row) {
            $code = trim((string)($row['ergonode_attribute_code'] ?? ''));
            if ($code !== '') {
                $existing[$code] = trim((string)($row['magento_attribute_code'] ?? ''));
            }
        }
        $verified = $this->metadataProvider->getVerifiedAttributeMap();
        $needed = [];
        foreach ($mappings as $mapping) {
            $code = trim((string)($mapping['left']['code'] ?? ''));
            $magentoCode = trim((string)($mapping['right']['code'] ?? ''));
            if ($code !== '' && !isset($verified[$code]) && ($existing[$code] ?? null) !== $magentoCode) {
                $needed[$code] = true;
            }
        }
        $this->metadataVerifier->verifyAttributes(array_keys($needed));
    }
}
