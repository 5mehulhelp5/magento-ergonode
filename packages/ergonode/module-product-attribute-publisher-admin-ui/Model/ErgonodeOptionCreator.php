<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisherAdminUi\Model;

use Ergonode\AttributePublisher\Api\Data\AttributeOptionStateInterface;
use Ergonode\AttributePublisherAdminUi\Model\ExistingSynchronizationNoticeResolver;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Framework\Exception\LocalizedException;
use Ergonode\ProductAttributePublisher\Api\OptionDefinitionPublisherInterface;

class ErgonodeOptionCreator
{
    public function __construct(
        private readonly OptionDefinitionPublisherInterface $definitionPublisher,
        private readonly PublishedMetadataVerifier $metadataVerifier,
        private readonly LanguageStoreMappingProviderInterface $languageMappingProvider,
        private readonly ExistingSynchronizationNoticeResolver $existingNoticeResolver
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function prepareMapping(AttributeOptionStateInterface $option): array
    {
        $language = $this->languageMappingProvider->requireAdminLanguageCode();
        $label = $option->getNames()[$language] ?? $option->getCode();

        return [
            'label' => $label, 'code' => $option->getCode(), 'type' => 'option',
            'scope' => $language,
            'source' => 'ergo', 'pending_create' => false,
        ];
    }

    public function synchronizeFromMagento(
        string $attributeCode,
        AttributeOptionStateInterface $option,
        string $sourceCode
    ): ?string {
        if (trim($attributeCode) === '') {
            throw new LocalizedException(__('Ergonode attribute code and Magento option code and label are required.'));
        }
        $result = $this->definitionPublisher->publish($attributeCode, $option);
        $this->verifyPublishedOptions($attributeCode);

        return $this->existingNoticeResolver->wasExisting($result, 'option_add:')
            ? (string)__(
                'Option "%1" already exists in Ergonode and has been linked to Magento option "%2".',
                $option->getCode(),
                $sourceCode
            )
            : null;
    }

    /**
     * @param array<string, mixed> $source
     */
    public function prepareState(
        string $magentoAttributeCode,
        string $ergonodeAttributeCode,
        array $source
    ): AttributeOptionStateInterface {
        return $this->definitionPublisher->prepareState($magentoAttributeCode, $ergonodeAttributeCode, $source);
    }

    public function verifyPublishedOptions(string $attributeCode): void
    {
        $this->metadataVerifier->verifyAttributes([$attributeCode]);
    }
}
