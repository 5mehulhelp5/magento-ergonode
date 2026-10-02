<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\ValueObject\File;

use InvalidArgumentException;

final readonly class FileUsageSet
{
    /** @var list<FileUsageReference> */
    private array $references;

    /** @param list<FileUsageReference> $references */
    public function __construct(array $references)
    {
        foreach ($references as $reference) {
            if (!$reference instanceof FileUsageReference) {
                throw new InvalidArgumentException('File usage set accepts only file usage references.');
            }
        }
        $this->references = array_values($references);
    }

    /** @return list<array{source_path: string, attribute_code: string, store_id: int}> */
    public function toRows(): array
    {
        return array_map(
            static fn (FileUsageReference $reference): array => $reference->toRow(),
            $this->references
        );
    }
}
