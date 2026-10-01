<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\Data;

use Ergonode\ProductPublisher\Api\Data\ProductAttributeValueInterface;

final readonly class ProductAttributeValuesResultDto
{
    /**
     * @param ProductAttributeValueInterface[] $values
     * @param string[] $warnings
     */
    public function __construct(private array $values, private array $warnings = [])
    {
    }

    /** @return ProductAttributeValueInterface[] */
    public function getValues(): array
    {
        return $this->values;
    }

    /** @return string[] */
    public function getWarnings(): array
    {
        return $this->warnings;
    }
}
