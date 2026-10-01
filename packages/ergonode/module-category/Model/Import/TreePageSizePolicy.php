<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Import;

use Magento\Framework\App\Config\ScopeConfigInterface;

class TreePageSizePolicy
{
    private const string INITIAL_SIZE_PATH = 'ergonode_categories/tree/initial_page_size';
    private const int DEFAULT_SIZE = 700;
    private const int MIN_SIZE = 100;
    private const int MAX_SIZE = 1000;
    private const int STEP = 100;

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function initialSize(): int
    {
        $value = filter_var($this->scopeConfig->getValue(self::INITIAL_SIZE_PATH), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => self::MIN_SIZE, 'max_range' => self::MAX_SIZE],
        ]);

        return $value === false ? self::DEFAULT_SIZE : $value;
    }

    public function nextSize(int $currentSize, int $receivedCount, float $seconds): int
    {
        if ($receivedCount !== $currentSize) {
            return $currentSize;
        }
        if ($seconds < 2.0) {
            return min(self::MAX_SIZE, $currentSize + self::STEP);
        }
        if ($seconds > 4.0) {
            // An emergency retry may already have reduced the size below the normal minimum.
            return max(min(self::MIN_SIZE, $currentSize), $currentSize - self::STEP);
        }

        return $currentSize;
    }

    /** @return int[] */
    public function retrySizes(int $requestedSize): array
    {
        return array_merge([$requestedSize], array_values(array_filter(
            [500, 200, 100, 50, 25],
            static fn (int $size): bool => $size < $requestedSize
        )));
    }
}
