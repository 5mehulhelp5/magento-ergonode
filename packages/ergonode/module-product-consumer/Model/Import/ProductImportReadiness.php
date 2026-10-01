<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Import;

use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\ProductAttributeConsumer\Api\ProductAttributeMappingProviderInterface;
use Ergonode\ProductConsumer\Api\ProductImportReadinessInterface;

class ProductImportReadiness implements ProductImportReadinessInterface
{
    public function __construct(
        private readonly ConfigProvider $configuration,
        private readonly LanguageStoreMappingProviderInterface $languages,
        private readonly ProductAttributeMappingProviderInterface $mappings
    ) {
    }

    public function getStatus(): array
    {
        $message = '';
        if (!$this->configuration->isEnabled() || trim($this->configuration->getApiKey()) === '') {
            $message = (string)__('Enable the Ergonode connection and configure credentials for the active mode.');
        } elseif ($this->languages->getAdminLanguageCode() === null) {
            $message = (string)__('Map the default Magento language before importing product data.');
        } elseif ($this->mappings->getMappings() === []) {
            $message = (string)__('Map product attributes before importing their values.');
        }

        return ['ready' => $message === '', 'message' => $message];
    }
}
