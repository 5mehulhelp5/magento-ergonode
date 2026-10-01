<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisher\Model\Data;

use Ergonode\CategoryPublisher\Api\Data\CategorySynchronizationContributionInterface;
use Ergonode\CategoryPublisher\Api\Data\CategoryStateInterface;
use InvalidArgumentException;

final readonly class CategoryStateDto implements CategoryStateInterface
{
    /** @var array<string, string> */
    private array $names;
    /** @var CategorySynchronizationContributionInterface[] */
    private array $contributions;

    /**
     * @param array<string, string> $names
     * @param CategorySynchronizationContributionInterface[] $contributions
     */
    public function __construct(
        private string $code,
        array $names = [],
        array $contributions = [],
        private bool $deleted = false
    ) {
        if (trim($this->code) === '') {
            throw new InvalidArgumentException('Category code cannot be empty.');
        }
        ksort($names);
        $this->names = $names;
        $seen = [];
        foreach ($contributions as $contribution) {
            if (!$contribution instanceof CategorySynchronizationContributionInterface) {
                throw new InvalidArgumentException(
                    'Category contributions must implement CategorySynchronizationContributionInterface.'
                );
            }
            $type = $contribution::class;
            if (isset($seen[$type])) {
                throw new InvalidArgumentException('Category contribution types must be unique.');
            }
            $seen[$type] = true;
        }
        $this->contributions = array_values($contributions);
    }

    public function getCode(): string
    {
        return trim($this->code);
    }
    public function getNames(): array
    {
        return $this->names;
    }
    public function getContributions(): array
    {
        return $this->contributions;
    }
    public function isDeleted(): bool
    {
        return $this->deleted;
    }
}
