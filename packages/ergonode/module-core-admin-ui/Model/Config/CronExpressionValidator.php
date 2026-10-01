<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Model\Config;

use Magento\Framework\Exception\LocalizedException;

class CronExpressionValidator
{
    private const array FIELD_RANGES = [
        [0, 59],
        [0, 23],
        [1, 31],
        [1, 12],
        [0, 6],
    ];

    private const array MONTHS = [
        'jan' => 1,
        'feb' => 2,
        'mar' => 3,
        'apr' => 4,
        'may' => 5,
        'jun' => 6,
        'jul' => 7,
        'aug' => 8,
        'sep' => 9,
        'oct' => 10,
        'nov' => 11,
        'dec' => 12,
    ];

    private const array WEEKDAYS = [
        'sun' => 0,
        'mon' => 1,
        'tue' => 2,
        'wed' => 3,
        'thu' => 4,
        'fri' => 5,
        'sat' => 6,
    ];

    public function validate(string $expression): string
    {
        $expression = trim($expression);
        $parts = preg_split('/\s+/', $expression) ?: [];
        if (count($parts) !== 5) {
            throw new LocalizedException(__('Cron schedule must contain exactly five fields.'));
        }
        foreach ($parts as $fieldIndex => &$part) {
            $part = strtolower($part);
            if (!$this->isValidField($part, $fieldIndex)) {
                throw new LocalizedException(__('Cron schedule contains an invalid field "%1".', $part));
            }
        }
        unset($part);

        return implode(' ', $parts);
    }

    private function isValidField(string $field, int $fieldIndex): bool
    {
        if ($field === '') {
            return false;
        }
        foreach (explode(',', $field) as $segment) {
            if (!$this->isValidSegment($segment, $fieldIndex)) {
                return false;
            }
        }

        return true;
    }

    private function isValidSegment(string $segment, int $fieldIndex): bool
    {
        $stepParts = explode('/', $segment);
        if (count($stepParts) > 2
            || (isset($stepParts[1]) && (!ctype_digit($stepParts[1]) || (int)$stepParts[1] <= 0))
        ) {
            return false;
        }
        $range = $stepParts[0];
        if ($range === '*') {
            return true;
        }
        $rangeParts = explode('-', $range);
        if (count($rangeParts) > 2) {
            return false;
        }
        $from = $this->numericValue($rangeParts[0], $fieldIndex);
        $to = isset($rangeParts[1]) ? $this->numericValue($rangeParts[1], $fieldIndex) : $from;

        return $from !== null && $to !== null && $from <= $to;
    }

    private function numericValue(string $value, int $fieldIndex): ?int
    {
        $names = match ($fieldIndex) {
            3 => self::MONTHS,
            4 => self::WEEKDAYS,
            default => [],
        };
        if (isset($names[$value])) {
            return $names[$value];
        }
        if (!ctype_digit($value)) {
            return null;
        }
        $numeric = (int)$value;
        [$minimum, $maximum] = self::FIELD_RANGES[$fieldIndex];

        return $numeric >= $minimum && $numeric <= $maximum ? $numeric : null;
    }
}
