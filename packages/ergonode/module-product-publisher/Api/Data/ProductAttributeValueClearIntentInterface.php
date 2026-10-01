<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Api\Data;

/**
 * Optional source intent carried by a product value.
 *
 * A missing translation means that the source did not provide it. A language
 * listed here explicitly requests deletion of the remote translation.
 */
interface ProductAttributeValueClearIntentInterface extends ProductAttributeValueInterface
{
    /**
     * @return string[]
     */
    public function getClearedLanguageCodes(): array;
}
