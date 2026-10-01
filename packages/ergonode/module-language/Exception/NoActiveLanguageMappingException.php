<?php

declare(strict_types=1);

namespace Ergonode\Language\Exception;

use Magento\Framework\Exception\LocalizedException;

class NoActiveLanguageMappingException extends LocalizedException
{
    public function __construct()
    {
        parent::__construct(__(
            'Configure at least one active Ergonode language mapped to an active Magento store scope.'
        ));
    }
}
