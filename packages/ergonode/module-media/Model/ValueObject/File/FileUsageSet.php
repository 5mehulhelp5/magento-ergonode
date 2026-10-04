<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\ValueObject\File;

use InvalidArgumentException;

final readonly class FileUsageSet
{
    /** @var list<FileUsageReference> */
    private array $references;
    private ?array $attributeCodes;
    private array $preservedAttributeCodes;

    /**
     * @param list<FileUsageReference> $references
     * @param list<string>|null $attributeCodes Null replaces all attributes; an empty list replaces none.
     * @param list<string> $preservedAttributeCodes Attributes whose desired state must remain untouched.
     */
    public function __construct(array $references, ?array $attributeCodes = null, array $preservedAttributeCodes = [])
    {
        foreach ($references as $reference) {
            if (!$reference instanceof FileUsageReference) {
                throw new InvalidArgumentException('File usage set accepts only file usage references.');
            }
            $code = $reference->toRow()['attribute_code'];
            if (($attributeCodes !== null && !in_array($code, $attributeCodes, true))
                || in_array($code, $preservedAttributeCodes, true)
            ) {
                throw new InvalidArgumentException('File usage reference is outside the synchronized attribute scope.');
            }
        }
        $this->references = array_values($references);
        $this->attributeCodes = $attributeCodes === null ? null : array_values(array_unique($attributeCodes));
        $this->preservedAttributeCodes = array_values(array_unique($preservedAttributeCodes));
    }

    public function attributeCodes(): ?array
    {
        return $this->attributeCodes;
    }

    public function preservedAttributeCodes(): array
    {
        return $this->preservedAttributeCodes;
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
