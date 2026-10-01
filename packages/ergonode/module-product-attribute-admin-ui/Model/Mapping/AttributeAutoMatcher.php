<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeAdminUi\Model\Mapping;

use Ergonode\ProductAttributeAdminUi\Api\ErgonodeMetadataProviderInterface;
use Ergonode\ProductAttribute\Model\Provider\MagentoAttributeProvider;
use Ergonode\ProductAttribute\Model\Mapping\AttributeMappingSuggester;

class AttributeAutoMatcher
{
    public function __construct(
        private readonly ErgonodeMetadataProviderInterface $ergonodeProvider,
        private readonly MagentoAttributeProvider $magentoProvider,
        private readonly AttributeMappingSuggester $suggester
    ) {
    }

    /**
     * @param  array<int, array<string, mixed>> $mappings
     * @param  array<int, array<string, mixed>> $visibility
     * @return array{
     *     matches: array<int, array{left: array<string, mixed>, right: array<string, mixed>}>,
     *     conflicts: array
     * }
     */
    public function suggest(array $mappings, array $visibility = []): array
    {
        return $this->suggester->suggest(
            $this->ergonodeProvider->getAttributeMap(),
            $this->magentoProvider->getAttributeMap(),
            $mappings,
            $visibility
        );
    }
}
