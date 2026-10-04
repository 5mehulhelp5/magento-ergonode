<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Exception;

use Magento\Framework\Exception\LocalizedException;

/** A configuration error requires correction before a new import is started. */
class InvalidMediaConfigurationException extends LocalizedException
{
}
