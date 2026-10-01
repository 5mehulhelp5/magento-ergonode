<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeAdminUi\Model;

use Collator;
use RuntimeException;

class AttributeListSorter
{
    private const string LOCALE = 'pl';

    private readonly Collator $collator;

    public function __construct()
    {
        $this->collator = new Collator(self::LOCALE);
        $this->collator->setStrength(Collator::PRIMARY);
        $this->collator->setAttribute(Collator::NUMERIC_COLLATION, Collator::ON);
    }

    /**
     * @template T of array{label: string}
     * @param    array<int, T> $attributes
     * @return   array<int, T>
     */
    public function sortByLabel(array $attributes): array
    {
        $indexedAttributes = [];
        foreach (array_values($attributes) as $index => $attribute) {
            $indexedAttributes[] = [
                'attribute' => $attribute,
                'index' => $index,
            ];
        }

        usort(
            $indexedAttributes,
            function (array $first, array $second): int {
                $labelComparison = $this->compareLabels(
                    (string)$first['attribute']['label'],
                    (string)$second['attribute']['label']
                );

                return $labelComparison !== 0
                    ? $labelComparison
                    : $first['index'] <=> $second['index'];
            }
        );

        $sortedAttributes = [];
        foreach ($indexedAttributes as $indexedAttribute) {
            $sortedAttributes[] = $indexedAttribute['attribute'];
        }

        return $sortedAttributes;
    }

    private function compareLabels(string $first, string $second): int
    {
        $comparison = $this->collator->compare(
            mb_strtolower(trim($first)),
            mb_strtolower(trim($second))
        );

        if ($comparison === false) {
            throw new RuntimeException('Unable to compare attribute labels.');
        }

        return $comparison;
    }
}
