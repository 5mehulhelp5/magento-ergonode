<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Api;

use Magento\Framework\Exception\LocalizedException;

enum UnmanagedImagesMode: string
{
    case Keep = 'keep';
    case Hide = 'hide';
    case Remove = 'remove';

    public static function fromConfig(mixed $value): self
    {
        if ($value === null || $value === '') {
            return self::Keep;
        }
        if (!is_string($value)) {
            throw new LocalizedException(__('Choose a valid mode for additional Magento images.'));
        }
        return self::tryFrom(trim($value))
            ?? throw new LocalizedException(__('Choose a valid mode for additional Magento images.'));
    }
}
