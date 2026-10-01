<?php

declare(strict_types=1);

namespace Ergonode\Core\Model\Import;

use Ergonode\Core\Api\SynchronizationOperationExecutorInterface;
use Ergonode\Core\Api\SynchronizationOperationInterface;
use InvalidArgumentException;
use Magento\Framework\Exception\LocalizedException;

class SynchronizationOperationExecutor implements SynchronizationOperationExecutorInterface
{
    /** @var array<string, SynchronizationOperationInterface> */
    private array $operations;

    /** @param array<string, SynchronizationOperationInterface> $operations */
    public function __construct(array $operations = [])
    {
        foreach ($operations as $processCode => $operation) {
            if (!is_string($processCode)
                || trim($processCode) === ''
                || trim($processCode) !== $processCode
                || !$operation instanceof SynchronizationOperationInterface
            ) {
                throw new InvalidArgumentException(
                    'Synchronization operations must use a process code and implement their API contract.'
                );
            }
        }

        $this->operations = $operations;
    }

    public function isAvailable(string $processCode): bool
    {
        return isset($this->operations[trim($processCode)]);
    }

    public function synchronize(string $processCode): void
    {
        $this->get($processCode)->synchronize();
    }

    public function resetCursor(string $processCode): void
    {
        $this->get($processCode)->resetCursor();
    }

    private function get(string $processCode): SynchronizationOperationInterface
    {
        $processCode = trim($processCode);
        if (!$this->isAvailable($processCode)) {
            throw new LocalizedException(
                __('Synchronization process "%1" is not available.', $processCode)
            );
        }

        return $this->operations[$processCode];
    }
}
