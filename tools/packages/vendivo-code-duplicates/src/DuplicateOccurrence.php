<?php

declare(strict_types=1);

namespace Vendivo\CodeDuplicates;

final readonly class DuplicateOccurrence
{
    public function __construct(
        public int $sequenceId,
        public int $startIndex,
        public int $endIndex,
        public string $file,
        public int $startLine,
        public int $endLine
    ) {
    }

    public function key(): string
    {
        return $this->sequenceId . ':' . $this->startIndex . ':' . $this->endIndex;
    }

    public function isContainedBy(self $other): bool
    {
        return $this->file === $other->file
            && $this->startLine >= $other->startLine
            && $this->endLine <= $other->endLine;
    }

    /** @return array{file: string, startLine: int, endLine: int} */
    public function toArray(): array
    {
        return [
            'file' => $this->file,
            'startLine' => $this->startLine,
            'endLine' => $this->endLine,
        ];
    }
}
