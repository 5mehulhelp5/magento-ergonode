<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\ValueObject\File;

use InvalidArgumentException;

final readonly class FileUsageReference
{
    public string $sourcePath;

    public string $attributeCode;

    public function __construct(string $sourcePath, string $attributeCode, public int $storeId)
    {
        $sourcePath = trim($sourcePath);
        $attributeCode = trim($attributeCode);
        if ($sourcePath === '' || $attributeCode === '') {
            throw new InvalidArgumentException('File usage path and attribute code cannot be empty.');
        }
        if ($this->storeId < 0) {
            throw new InvalidArgumentException('File usage store ID cannot be negative.');
        }
        $this->sourcePath = $sourcePath;
        $this->attributeCode = $attributeCode;
    }

    /** @return array{source_path: string, attribute_code: string, store_id: int} */
    public function toRow(): array
    {
        return [
            'source_path' => $this->sourcePath,
            'attribute_code' => $this->attributeCode,
            'store_id' => $this->storeId,
        ];
    }
}
