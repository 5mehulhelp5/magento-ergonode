<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\Sync;

use Ergonode\ProductPublisher\Api\Data\ProductStateInterface;

final readonly class ProductIdentityPreparationResult
{
    /**
     * @param array<string, ProductStateInterface> $states Magento SKU to remote-identity state.
     * @param array<string, array{status: string, message: string}> $failures Magento SKU to terminal issue.
     */
    public function __construct(private array $states, private array $failures)
    {
    }

    /** @return array<string, ProductStateInterface> */
    public function getStates(): array
    {
        return $this->states;
    }

    /** @return array<string, array{status: string, message: string}> */
    public function getFailures(): array
    {
        return $this->failures;
    }
}
