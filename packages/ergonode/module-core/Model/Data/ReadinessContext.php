<?php

declare(strict_types=1);

namespace Ergonode\Core\Model\Data;

use Ergonode\Core\Api\Data\ReadinessContextInterface;
use InvalidArgumentException;

final readonly class ReadinessContext implements ReadinessContextInterface
{
    /** @param array<string, string[]> $entityIdentifiers */
    public function __construct(
        private string $operation,
        private array $entityIdentifiers = []
    ) {
        if (trim($operation) === '') {
            throw new InvalidArgumentException('Readiness operation must not be empty.');
        }
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function getEntityIdentifiers(string $domain): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (string $identifier): string => trim($identifier),
            $this->entityIdentifiers[$domain] ?? []
        ))));
    }
}
