<?php

declare(strict_types=1);

namespace PackHauer\FileAttribute\Model\Attribute;

use Magento\Eav\Model\Entity\Setup\PropertyMapperInterface;
use PackHauer\FileAttribute\Model\Attribute\Backend\File;

class FilePropertyMapper implements PropertyMapperInterface
{
    /** @return array<string, string> */
    public function map(array $input, $entityTypeId): array
    {
        if (($input['input'] ?? null) !== 'file') {
            return [];
        }

        return [
            'backend_model' => File::class,
            'backend_type' => 'varchar',
        ];
    }
}
