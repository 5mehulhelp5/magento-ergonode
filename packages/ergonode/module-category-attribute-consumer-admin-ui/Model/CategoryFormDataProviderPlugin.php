<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumerAdminUi\Model;

use Ergonode\Category\Api\CategoryFormContextProviderInterface;
use Magento\Catalog\Model\Category\DataProvider;

class CategoryFormDataProviderPlugin
{
    public function __construct(
        private readonly CategoryFormContextProviderInterface $contextProvider
    ) {
    }

    /**
     * @param array<int|string, array<string, mixed>>|null $result
     * @return array<int|string, array<string, mixed>>|null
     */
    public function afterGetData(DataProvider $subject, ?array $result): ?array
    {
        if ($result === null) {
            return null;
        }
        foreach ($result as $key => $categoryData) {
            $categoryId = (int)($categoryData['id'] ?? $categoryData['entity_id'] ?? $key);
            $context = $this->contextProvider->getForMagentoCategory($categoryId);
            $result[$key]['ergonode_category_code'] = $context['ergonode_category_code'] ?? '';
            $result[$key]['ergonode_category_tree_id'] = $context['category_tree_id'] ?? 0;
        }

        return $result;
    }
}
