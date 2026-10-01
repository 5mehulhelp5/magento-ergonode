<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeAdminUi\Model\Mapping;

use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\ProductAttribute\Api\MappingReaderInterface;
use Ergonode\ProductAttribute\Api\OptionAutoMatcherInterface;
use Ergonode\ProductAttribute\Model\Provider\MagentoOptionProvider;
use Ergonode\ProductAttributeAdminUi\Api\ErgonodeMetadataProviderInterface;
use Magento\Framework\Exception\LocalizedException;

class OptionAutoMatchProcess
{
    public function __construct(
        private readonly MappingReaderInterface $mappingReader,
        private readonly ErgonodeMetadataProviderInterface $ergonodeMetadata,
        private readonly MagentoOptionProvider $magentoOptions,
        private readonly LanguageStoreMappingProviderInterface $languages,
        private readonly OptionAutoMatcherInterface $matcher,
        private readonly RemoteAttributeMetadataSource $remoteSource,
        private readonly ConfigProvider $config
    ) {
    }

    /**
     * @param list<string> $availableErgonodeCodes
     * @param list<string> $availableMagentoCodes
     * @return array<string, mixed>
     */
    public function suggest(int $mappingId, array $availableErgonodeCodes, array $availableMagentoCodes): array
    {
        $mapping = $this->mappingReader->getAttributeRow($mappingId);
        if ($mapping === null) {
            throw new LocalizedException(__('Attribute mapping "%1" does not exist.', $mappingId));
        }
        if ($availableErgonodeCodes === [] || $availableMagentoCodes === []) {
            return ['matches' => [], 'conflicts' => [], 'unmatched' => []];
        }

        $attributeCode = (string)$mapping['ergonode_attribute_code'];
        $ergonodeOptions = $this->ergonodeMetadata->getVerifiedOptions($attributeCode);
        if ($ergonodeOptions === [] && $this->config->isEnabled()) {
            $this->remoteSource->refreshOptions($attributeCode);
            $ergonodeOptions = $this->ergonodeMetadata->getVerifiedOptions($attributeCode);
        }
        $availableErgonodeCodes = array_fill_keys($availableErgonodeCodes, true);
        $ergonodeOptions = $this->availableOptions($ergonodeOptions, $availableErgonodeCodes, 'ergo');
        $magentoOptions = $this->magentoOptions->getOptions((string)$mapping['magento_attribute_code']);
        $availableMagentoCodes = array_fill_keys($availableMagentoCodes, true);
        $magentoOptions = $this->availableOptions($magentoOptions, $availableMagentoCodes, 'magento');

        $primary = $this->matcher->suggest($mappingId, $ergonodeOptions, $magentoOptions);
        $storeLanguages = array_filter(
            $this->languages->getLanguageStoreMap(),
            static fn (string $language, int $storeId): bool => $storeId > 0 && $language !== '',
            ARRAY_FILTER_USE_BOTH
        );
        if ($primary['unmatched'] === [] || $storeLanguages === []) {
            return $primary;
        }

        $remoteOptions = $this->remoteSource->getOptions($attributeCode);
        if ($this->config->isEnabled() && $this->needsLanguageNames($remoteOptions, $ergonodeOptions)) {
            $this->remoteSource->refreshOptions($attributeCode);
            $ergonodeOptions = $this->availableOptions(
                $this->ergonodeMetadata->getVerifiedOptions($attributeCode),
                $availableErgonodeCodes,
                'ergo'
            );
        }

        return $this->matcher->suggest(
            $mappingId,
            $ergonodeOptions,
            $magentoOptions,
            $storeLanguages
        );
    }

    /**
     * @param array<int|string, array<string, mixed>> $options
     * @param array<string, bool> $availableCodes
     * @return array<int|string, array<string, mixed>>
     */
    private function availableOptions(array $options, array $availableCodes, string $source): array
    {
        $result = [];
        foreach ($options as $key => $option) {
            if (!isset($availableCodes[(string)($option['code'] ?? '')])) {
                continue;
            }
            $option['source'] = $source;
            $result[$key] = $option;
        }

        return $result;
    }

    /** @param array<int, array<string, mixed>> $remote @param array<int|string, array<string, mixed>> $verified */
    private function needsLanguageNames(array $remote, array $verified): bool
    {
        if ($remote === []) {
            return true;
        }
        foreach ([$remote, $verified] as $options) {
            foreach ($options as $option) {
                if (!array_key_exists('names', $option)) {
                    return true;
                }
            }
        }

        return false;
    }
}
