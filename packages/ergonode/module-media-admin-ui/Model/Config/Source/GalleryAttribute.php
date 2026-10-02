<?php

declare(strict_types=1);

namespace Ergonode\MediaAdminUi\Model\Config\Source;

use Ergonode\Media\Api\GalleryAttributeOptionsInterface;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Exception\LocalizedException;

class GalleryAttribute implements OptionSourceInterface
{
    public function __construct(private readonly GalleryAttributeOptionsInterface $attributes)
    {
    }

    /** @return list<array{value: string, label: string}> */
    public function toOptionArray(): array
    {
        $options = [['value' => '', 'label' => (string)__('Choose a gallery attribute')]];
        try {
            $attributes = $this->attributes->getOptions();
        } catch (LocalizedException $exception) {
            return [['value' => '', 'label' => (string)__('Gallery list unavailable: %1', $exception->getMessage())]];
        }
        foreach ($attributes as $code => $label) {
            $options[] = ['value' => (string)$code, 'label' => $label];
        }

        return $options;
    }
}
