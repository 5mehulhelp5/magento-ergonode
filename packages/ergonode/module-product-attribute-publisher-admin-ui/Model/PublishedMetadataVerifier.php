<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisherAdminUi\Model;

use Ergonode\AttributePublisher\Api\AttributeBatchStateLoaderInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeOptionStateInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeStateInterface;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\ProductAttributeAdminUi\Api\VerifiedErgonodeMetadataRecorderInterface;
use Magento\Framework\Exception\LocalizedException;

class PublishedMetadataVerifier
{
    public function __construct(
        private readonly AttributeBatchStateLoaderInterface $stateLoader,
        private readonly VerifiedErgonodeMetadataRecorderInterface $recorder,
        private readonly LanguageStoreMappingProviderInterface $languageProvider
    ) {
    }

    /** @param string[] $codes */
    public function verifyAttributes(array $codes): void
    {
        if ($codes === []) {
            return;
        }
        foreach ($this->stateLoader->loadBatch($codes) as $code => $state) {
            if ($state === null) {
                throw new LocalizedException(__('Ergonode attribute "%1" is unavailable.', $code));
            }
            $this->recordAttribute($state);
        }
    }

    private function recordAttribute(AttributeStateInterface $state): void
    {
        $this->recorder->recordAttribute([
            'label' => $this->label($state->getNames(), $state->getCode()),
            'code' => $state->getCode(),
            'type' => $state->getType(),
            'scope' => $state->getScope(),
            'active' => true,
            'parameters' => $state->getParameters(),
        ]);
        $this->recordOptions($state->getCode(), $state->getOptions());
    }

    /** @param AttributeOptionStateInterface[] $options */
    private function recordOptions(string $attributeCode, array $options): void
    {
        $metadata = [];
        foreach ($options as $option) {
            $metadata[] = [
                'label' => $this->label($option->getNames(), $option->getCode()),
                'code' => $option->getCode(),
                'type' => 'option',
                'scope' => $this->languageProvider->getAdminLanguageCode() ?? '',
                'active' => true,
            ];
        }
        $this->recorder->recordOptions($attributeCode, $metadata);
    }

    /** @param array<string, string> $names */
    private function label(array $names, string $fallback): string
    {
        $language = $this->languageProvider->getAdminLanguageCode();

        return (string)(($language !== null ? $names[$language] ?? null : null)
            ?? reset($names)
            ?: $fallback);
    }
}
