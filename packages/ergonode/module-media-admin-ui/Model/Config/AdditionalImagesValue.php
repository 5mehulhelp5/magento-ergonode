<?php

declare(strict_types=1);

namespace Ergonode\MediaAdminUi\Model\Config;

use Ergonode\Media\Api\ImageAttributeOptionsInterface;
use Ergonode\ProductMedia\Api\ImageRulesNormalizerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Exception\LocalizedException;

class AdditionalImagesValue
{
    public function __construct(
        private readonly ImageAttributeOptionsInterface $attributes,
        private readonly ImageRulesNormalizerInterface $normalizer,
        private readonly Json $json
    ) {
    }
    /** @param array<string|int,array{attribute?:string,position?:string|int}> $value */
    public function serialize(array $value): string
    {
        $rows = $this->normalizer->normalize($value);
        $available = $rows === [] ? [] : $this->attributes->getOptions();
        foreach ($rows as $row) {
            if (!array_key_exists($row['attribute'], $available)) {
                throw new LocalizedException(__('The selected Image attribute is no longer available.'));
            }
        }
        return $this->json->serialize($rows);
    }
    /** @return list<array{attribute:string,position:int}> */
    public function decode(string $value): array
    {
        return $value === '' ? [] : $this->json->unserialize($value);
    }
}
