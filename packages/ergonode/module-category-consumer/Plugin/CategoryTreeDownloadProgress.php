<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Plugin;

use Ergonode\Category\Model\Import\CategoryTreePageReader;
use Ergonode\CategoryConsumer\Model\Sync\CategorySynchronizationProgress;

/** Observes the existing category page reader without changing transport or pagination. */
class CategoryTreeDownloadProgress
{
    public function __construct(private readonly CategorySynchronizationProgress $progress)
    {
    }

    /**
     * @param array{data: array<string, mixed>, seconds: float} $result
     * @return array{data: array<string, mixed>, seconds: float}
     */
    public function afterRead(
        CategoryTreePageReader $subject,
        array $result,
        string $treeCode,
        ?string $cursor
    ): array {
        $edges = (array)($result['data']['categoryTree']['categoryTreeLeafList']['edges'] ?? []);
        $categories = count(array_filter(
            $edges,
            static fn (mixed $edge): bool => is_array($edge) && is_array($edge['node'] ?? null)
        ));
        $this->progress->downloadedPage($treeCode, $cursor === null, $categories);

        return $result;
    }
}
