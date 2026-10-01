<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Model\Data;

use Ergonode\Publisher\Api\Data\MutationResultInterface;
use Ergonode\Publisher\Api\Data\SynchronizationResultInterface;

final readonly class SynchronizationResult implements SynchronizationResultInterface
{
    /**
     * @param MutationResultInterface[] $results
     */
    public function __construct(private array $results)
    {
    }

    /**
     * @return MutationResultInterface[]
     */
    public function getResults(): array
    {
        return $this->results;
    }

    public function isSuccessful(): bool
    {
        foreach ($this->results as $result) {
            if ($result->getStatus() !== MutationResultInterface::STATUS_SUCCESS) {
                return false;
            }
        }

        return true;
    }
}
