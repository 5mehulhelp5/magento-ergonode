<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisher\Model\Data;

use Ergonode\CategoryAttributePublisher\Api\Data\CategoryAttributeStateInterface;
use Ergonode\CategoryAttributePublisher\Api\Data\CategoryAttributeValueInterface;
use InvalidArgumentException;

final readonly class CategoryAttributeStateDto implements CategoryAttributeStateInterface
{
    /** @var string[] */
    private array $allowedAttributeCodes;

    /** @var CategoryAttributeValueInterface[] */
    private array $values;

    /**
     * @param string[] $allowedAttributeCodes
     * @param CategoryAttributeValueInterface[] $values
     */
    public function __construct(array $allowedAttributeCodes = [], array $values = [])
    {
        $allowedAttributeCodes = array_values(array_unique(array_filter(
            array_map(static fn (string $code): string => trim($code), $allowedAttributeCodes),
            static fn (string $code): bool => $code !== ''
        )));
        sort($allowedAttributeCodes);
        $this->allowedAttributeCodes = $allowedAttributeCodes;

        $seen = [];
        foreach ($values as $value) {
            if (!$value instanceof CategoryAttributeValueInterface || isset($seen[$value->getAttributeCode()])) {
                throw new InvalidArgumentException('Category values must have unique attribute codes.');
            }
            $seen[$value->getAttributeCode()] = true;
        }
        $this->values = array_values($values);
    }

    public function getAllowedAttributeCodes(): array
    {
        return $this->allowedAttributeCodes;
    }

    public function getValues(): array
    {
        return $this->values;
    }
}
