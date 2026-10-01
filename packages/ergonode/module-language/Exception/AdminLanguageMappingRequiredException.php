<?php

declare(strict_types=1);

namespace Ergonode\Language\Exception;

use Magento\Framework\Exception\LocalizedException;

class AdminLanguageMappingRequiredException extends LocalizedException
{
    public function __construct()
    {
        parent::__construct(__(
            'This operation requires an active Ergonode language mapped to Magento store ID 0.'
        ));
    }
}
