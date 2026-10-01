<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Model\ManualTree;

use Ergonode\Category\Api\CategoryCreationContextProviderInterface;
use Ergonode\Category\Api\CategoryLayoutSaverInterface;
use Magento\Framework\Exception\LocalizedException;

class CategoryFormPublisher
{
    public function __construct(
        private readonly CategoryCreationContextProviderInterface $contextProvider,
        private readonly CategoryBatchPublisher $batchPublisher,
        private readonly CategoryLayoutSaverInterface $categoryLayoutSaver,
        private readonly CategoryCodeGenerator $codeGenerator
    ) {
    }

    public function publish(int $categoryId): string
    {
        if ($categoryId <= 0) {
            throw new LocalizedException(__('Category is required.'));
        }

        $context = $this->contextProvider->getForMagentoCategory($categoryId);
        if ($context === null) {
            throw new LocalizedException(__(
                'The Magento category does not belong to an active Ergonode Category Tree.'
            ));
        }
        if ($context['mapped_code'] !== null) {
            return $context['mapped_code'];
        }

        $item = $this->createItem($context);
        $allowExistingCodes = $context['pending_code'] !== null ? [$item['code']] : [];
        $results = $this->batchPublisher->publish(
            $context['category_tree_id'],
            [$item],
            $allowExistingCodes
        );
        $result = $results[0] ?? [];
        if (in_array(($result['status'] ?? 'failed'), ['failed', 'skipped'], true)) {
            throw new LocalizedException(__(
                'Unable to create the Ergonode category: %1',
                (string)($result['message'] ?? __('Unknown error.'))
            ));
        }
        $remoteId = trim((string)($result['remote_id'] ?? ''));
        if ($remoteId === '') {
            throw new LocalizedException(__('Ergonode did not return the created category identifier.'));
        }

        $item['extension_data']['to_ergonode']['remote_prepared'] = true;
        $item['ergonode_category_id'] = $remoteId;
        $items = $context['items'];
        $items[] = $item;
        $this->categoryLayoutSaver->save($context['category_tree_id'], $items);

        return (string)$item['code'];
    }

    /**
     * @param array{
     *     pending_code: string|null,
     *     category: array{
     *         id: int,
     *         label: string,
     *         url_key: string,
     *         position: int,
     *         path_ids: int[],
     *         path_labels: string[]
     *     },
     *     items: array<int, array<string, mixed>>
     * } $context
     * @return array<string, mixed>
     */
    private function createItem(array $context): array
    {
        $category = $context['category'];
        $code = $context['pending_code'] ?? $this->codeGenerator->generate($category['path_labels']);

        return [
            'code' => $code,
            'label' => $category['label'],
            'parent_code' => $this->nearestMappedParentCode($category['path_ids'], $context['items']),
            'sort_order' => $category['position'],
            'magento_category_id' => $category['id'],
            'extension_data' => [
                'to_ergonode' => [
                    'pending_create' => true,
                    'label' => $category['label'],
                ],
            ],
        ];
    }

    /**
     * @param int[] $pathIds
     * @param array<int, array<string, mixed>> $items
     */
    private function nearestMappedParentCode(array $pathIds, array $items): ?string
    {
        $mappedCodes = [];
        foreach ($items as $item) {
            $magentoCategoryId = (int)($item['magento_category_id'] ?? 0);
            $code = trim((string)($item['code'] ?? ''));
            if ($magentoCategoryId > 0 && $code !== '') {
                $mappedCodes[$magentoCategoryId] = $code;
            }
        }

        array_pop($pathIds);
        foreach (array_reverse($pathIds) as $pathId) {
            if (isset($mappedCodes[$pathId])) {
                return $mappedCodes[$pathId];
            }
        }

        return null;
    }
}
