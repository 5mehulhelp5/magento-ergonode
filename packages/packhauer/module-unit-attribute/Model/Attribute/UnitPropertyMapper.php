<?php

declare(strict_types=1);

namespace PackHauer\UnitAttribute\Model\Attribute;

use Magento\Eav\Model\Entity\Setup\PropertyMapperInterface;
use PackHauer\UnitAttribute\Model\Attribute\Backend\Unit;

class UnitPropertyMapper implements PropertyMapperInterface
{
    /**
     * @return array<string, mixed>
     */
    public function map(array $input, $entityTypeId): array
    {
        if (($input['input'] ?? null) !== 'unit') {
            return [];
        }

        $mapped = [
            'backend_model' => Unit::class,
            'backend_type' => 'decimal',
        ];
        if (isset($input['additional_data'])) {
            $mapped['additional_data'] = $input['additional_data'];
        }

        return $mapped;
    }
}
