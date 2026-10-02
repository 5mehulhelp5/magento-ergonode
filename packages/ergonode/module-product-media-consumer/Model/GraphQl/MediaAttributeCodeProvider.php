<?php

declare(strict_types=1);

namespace Ergonode\ProductMediaConsumer\Model\GraphQl;

use Ergonode\ProductMedia\Api\GalleryRulesInterface;

use Ergonode\ProductMedia\Api\GalleryConfigurationInterface;
use Ergonode\ProductConsumer\Api\ProductAttributeCodeProviderInterface;
use Magento\Framework\Exception\LocalizedException;

class MediaAttributeCodeProvider implements ProductAttributeCodeProviderInterface
{
    public function __construct(
        private readonly GalleryConfigurationInterface $configuration,
        private readonly GalleryRulesInterface $rules
    ) {
    }

    public function getAttributeCodes(): array
    {
        if (!$this->configuration->isSynchronizationEnabled()) {
            return [];
        }
        $code = $this->configuration->getGalleryAttributeCode();
        if ($code === '') {
            throw new LocalizedException(__('Choose an Ergonode gallery attribute before synchronizing media.'));
        }

        return array_values(array_unique([$code, ...array_keys($this->rules->getAdditionalImages())]));
    }
}
