<?php

declare(strict_types=1);

namespace Ergonode\ProductMediaConsumer\Model\Media;

use Ergonode\ProductMedia\Api\GalleryLayoutInterface;

use Ergonode\Attribute\Api\ErgonodeAttributeTypeInterface;
use Ergonode\ProductMedia\Api\GalleryConfigurationInterface;
use Ergonode\Media\Model\ValueObject\Gallery\GallerySelection;
use Ergonode\ProductConsumer\Model\ValueObject\Product\Attribute\LocalizedStringListValues;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProductAttribute;
use LogicException;

class ProductGallerySelectionProvider
{
    public function __construct(
        private readonly GalleryConfigurationInterface $configuration,
        private readonly AdditionalImageSelection $additionalImages,
        private readonly GalleryLayoutInterface $layout
    ) {
    }

    /** @param RemoteProductAttribute[] $attributes */
    public function provide(array $attributes): ?GallerySelection
    {
        if (!$this->configuration->isSynchronizationEnabled()) {
            return null;
        }
        $code = $this->configuration->getGalleryAttributeCode();
        if ($code === '') {
            throw new LogicException('Choose an Ergonode gallery attribute before synchronizing media.');
        }
        foreach ($attributes as $attribute) {
            if ($attribute->code !== $code) {
                continue;
            }
            if ($attribute->type !== ErgonodeAttributeTypeInterface::TYPE_GALLERY) {
                throw new LogicException('The configured Ergonode gallery attribute has changed type.');
            }
            if (!$attribute->values instanceof LocalizedStringListValues) {
                throw new LogicException('Remote gallery attribute must contain localized path lists.');
            }
            $paths = [];
            foreach ($attribute->values->all() as $localizedPaths) {
                $paths = [...$paths, ...$localizedPaths];
            }

            return new GallerySelection($this->layout->arrange(
                $paths,
                $this->additionalImages->positions($attributes)
            ));
        }

        return null;
    }
}
