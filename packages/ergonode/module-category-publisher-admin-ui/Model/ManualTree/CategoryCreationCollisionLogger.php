<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Model\ManualTree;

use Psr\Log\LoggerInterface;

class CategoryCreationCollisionLogger
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function warning(
        int $categoryTreeId,
        string $code,
        ?int $skippedMagentoCategoryId,
        string $skippedLabel,
        string $collisionSource,
        ?int $winningMagentoCategoryId = null,
        ?string $winningLabel = null
    ): void {
        $this->logger->warning('Magento category creation in Ergonode was skipped due to a code collision.', [
            'category_tree_id' => $categoryTreeId,
            'ergonode_category_code' => $code,
            'skipped_magento_category_id' => $skippedMagentoCategoryId,
            'skipped_magento_category_label' => $skippedLabel,
            'collision_source' => $collisionSource,
            'winning_magento_category_id' => $winningMagentoCategoryId,
            'winning_magento_category_label' => $winningLabel,
        ]);
    }
}
