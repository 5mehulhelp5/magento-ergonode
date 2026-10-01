<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\Source;

use Ergonode\ProductPublisher\Api\Data\ProductStateInterface;
use InvalidArgumentException;

use function is_string;
use function trim;

final readonly class ProductSourceResult
{
    /**
     * @param ProductStateInterface[] $states
     * @param bool $authoritative Whether the requested source scope is complete.
     * @param array<int|string, string> $skippedProductMessages
     * @param array<int|string, string[]> $productWarnings
     * @param array<int|string, string> $skippedProductWarnings
     */
    public function __construct(
        private array $states,
        private bool $authoritative,
        private array $skippedProductMessages = [],
        private array $productWarnings = [],
        private array $skippedProductWarnings = []
    ) {
        foreach ($states as $state) {
            if (!$state instanceof ProductStateInterface) {
                throw new InvalidArgumentException('Product source result accepts only product states.');
            }
        }
        foreach ($skippedProductMessages as $sku => $message) {
            if (trim((string)$sku) === '' || !is_string($message) || trim($message) === '') {
                throw new InvalidArgumentException('Skipped products need non-empty SKUs and messages.');
            }
        }
    }

    /** @return ProductStateInterface[] */
    public function getStates(): array
    {
        return $this->states;
    }

    /** Return whether the requested product scope was fully represented. */
    public function isAuthoritative(): bool
    {
        return $this->authoritative;
    }

    /** @return array<int|string, string> */
    public function getSkippedProductMessages(): array
    {
        return $this->skippedProductMessages;
    }

    /** @return array<int|string, string[]> */
    public function getProductWarnings(): array
    {
        return $this->productWarnings;
    }

    /** @return array<int|string, string> */
    public function getSkippedProductWarnings(): array
    {
        return $this->skippedProductWarnings;
    }
}
