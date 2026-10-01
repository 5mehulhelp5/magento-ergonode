<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisherAdminUi\Model;

use Ergonode\AttributePublisherAdminUi\Model\AttributeMappingPayloadBuilder;
use Ergonode\AttributePublisherAdminUi\Model\ExistingSynchronizationNoticeResolver;
use Ergonode\AttributePublisher\Api\Data\AttributeStateInterface;
use Ergonode\ProductAttributePublisher\Api\AttributeDefinitionPublisherInterface;

class ErgonodeAttributeCreator
{
    public function __construct(
        private readonly AttributeDefinitionPublisherInterface $definitionPublisher,
        private readonly AttributeMappingPayloadBuilder $mappingPayloadBuilder,
        private readonly PublishedMetadataVerifier $metadataVerifier,
        private readonly ExistingSynchronizationNoticeResolver $existingNoticeResolver
    ) {
    }

    /**
     * @param  array<string, mixed> $source
     * @return array<string, mixed>
     */
    public function prepareMapping(array $source): array
    {
        return $this->mappingPayloadBuilder->build($source);
    }

    /**
     * @param array<string, mixed> $source
     */
    public function synchronizeFromMagento(array $source): ?string
    {
        $state = $this->prepareState($source);
        $result = $this->definitionPublisher->publish($state);
        $this->verifyPublishedState($state);

        return $this->existingNoticeResolver->wasExisting($result, 'create')
            ? (string)__(
                'Attribute "%1" already exists in Ergonode and has been linked to Magento attribute "%2".',
                $state->getCode(),
                trim((string)($source['code'] ?? ''))
            )
            : null;
    }

    /**
     * @param array<string, mixed> $source
     */
    public function prepareState(array $source): AttributeStateInterface
    {
        return $this->definitionPublisher->prepareState($source);
    }

    public function verifyPublishedState(AttributeStateInterface $state): void
    {
        $this->metadataVerifier->verifyAttributes([$state->getCode()]);
    }
}
