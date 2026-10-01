<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Mapping;

use Ergonode\Category\Api\CategoryMappingSaveHandlerInterface;

class CategoryMappingSaveHandler implements CategoryMappingSaveHandlerInterface
{
    public function __construct(
        private readonly CategoryMappingDataPreparer $dataPreparer,
        private readonly CategoryMappingDataWriter $dataWriter
    ) {
    }

    public function save(int $categoryTreeId, callable $saveLayout, array $newMappings): array
    {
        return $this->dataWriter->save($categoryTreeId, $saveLayout, $this->dataPreparer->prepare($newMappings));
    }
}
