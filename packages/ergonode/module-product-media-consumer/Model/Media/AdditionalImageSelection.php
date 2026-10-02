<?php

declare(strict_types=1);

namespace Ergonode\ProductMediaConsumer\Model\Media;

use Ergonode\ProductMedia\Api\GalleryRulesInterface;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProductAttribute;
use Ergonode\ProductConsumer\Model\ValueObject\Product\Attribute\LocalizedStringValues;
use LogicException;

class AdditionalImageSelection
{
    public function __construct(private readonly GalleryRulesInterface $rules)
    {
    }
    /** @param RemoteProductAttribute[] $attributes
     * @return array<string,int>
     */
    public function positions(array $attributes): array
    {
        $sources = [];
        foreach ($attributes as $attribute) {
            $sources[$attribute->code] = $attribute;
        }
        $result = [];
        foreach ($this->rules->getAdditionalImages() as $code => $position) {
            $source = $sources[$code] ?? null;
            if ($source === null || $source->type !== 'image' || !$source->values instanceof LocalizedStringValues) {
                throw new LogicException('A configured Image attribute is absent or has changed type: ' . $code);
            }
            $paths = array_values(array_unique(array_filter(array_map('trim', $source->values->all()))));
            foreach ($paths as $offset => $path) {
                $result[$path] = $position + $offset;
            }
        }
        return $result;
    }
}
