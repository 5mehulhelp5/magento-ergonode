<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Plugin;

use Ergonode\Category\Model\Mapping\CategoryLayoutSaver;
use Ergonode\Category\Api\CategoryLayoutValidatorInterface;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryTreePublisher;

class CategoryLayoutSaverPlugin
{
    public function __construct(
        private readonly CategoryTreePublisher $categoryTreePublisher,
        private readonly CategoryLayoutValidatorInterface $validator
    ) {
    }

    /**
     * @param callable(int, array, array): array $proceed
     * @param array<int, array<string, mixed>> $items
     * @param array<int, array<string, mixed>> $visibility
     * @return array{updated: int, unchanged: int, attribute_values: int}
     */
    public function aroundSave(
        CategoryLayoutSaver $subject,
        callable $proceed,
        int $categoryTreeId,
        array $items,
        array $visibility = []
    ): array {
        $this->validator->validate($categoryTreeId, $items);
        $published = $this->categoryTreePublisher->requiresManualWrite($categoryTreeId, $items);
        if ($published) {
            $this->categoryTreePublisher->publish($categoryTreeId, $items);
        }

        $result = $proceed($categoryTreeId, $items, $visibility);
        if ($published) {
            $this->categoryTreePublisher->complete($categoryTreeId, $items);
        }
        return $result;
    }
}
