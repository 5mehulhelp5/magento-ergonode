<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Provider;

use Ergonode\Category\Model\CategoryTree\CategoryTreeSourceState;
use Ergonode\Category\Model\Provider\CategoryMappingQuery;

class CategorySourceIssueProvider
{
    public function __construct(
        private readonly CategoryTreeSourceState $sourceState,
        private readonly CategoryMappingQuery $mappings,
        private readonly MagentoCategoryProvider $magentoCategories
    ) {
    }

    /** @return array<string, mixed> */
    public function get(int $treeId, int $rootId): array
    {
        $state = $this->sourceState->get($treeId);
        $missing = [];
        if ($state['snapshot_at'] !== null && !$state['requires_refresh']) {
            $absent = array_fill_keys($state['missing_codes'], true);
            $magento = $this->magentoCategories->getCategories($rootId);
            foreach ($this->mappings->getMappingsByTreeId($treeId) as $code => $categoryId) {
                if (isset($absent[$code]) && isset($magento[$categoryId])) {
                    $missing[] = [
                        'code' => $code,
                        'magento_category_id' => $categoryId,
                        'label' => (string)$magento[$categoryId]['label'],
                    ];
                }
            }
        }

        return $state + ['missing_categories' => $missing];
    }
}
