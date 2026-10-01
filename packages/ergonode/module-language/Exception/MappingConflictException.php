<?php

declare(strict_types=1);

namespace Ergonode\Language\Exception;

use Magento\Framework\Exception\LocalizedException;

class MappingConflictException extends LocalizedException
{
    public function __construct()
    {
        parent::__construct(__(
            'Language mappings have changed in another session. Reload the page before making further changes.'
        ));
    }
}
