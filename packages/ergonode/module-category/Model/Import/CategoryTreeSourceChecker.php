<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Import;

use Ergonode\Category\Model\CategoryTree\CategoryTreeSourceState;
use Ergonode\Core\Api\GraphQlQueryClientInterface;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class CategoryTreeSourceChecker
{
    private const string QUERY = <<<'GRAPHQL'
query ErgonodeCategoryTreePresence($code: CategoryTreeCode!) {
  categoryTree(code: $code) { code }
}
GRAPHQL;

    public function __construct(
        private readonly GraphQlQueryClientInterface $client,
        private readonly CategoryTreeSourceState $sourceState,
        private readonly CategoryTreeDownloadScope $downloadScope
    ) {
    }

    public function check(int $treeId, string $treeCode): void
    {
        try {
            $data = $this->downloadScope->presence(
                $treeCode,
                fn (): array => $this->client->query(self::QUERY, ['code' => $treeCode])
            );
            if (!array_key_exists('categoryTree', $data)) {
                throw new LocalizedException(__('Unable to verify the Ergonode category tree.'));
            }
            if ($data['categoryTree'] === null) {
                throw new MissingCategoryTreeException(
                    __(
                        'Ergonode category tree "%1" was not found. '
                        . 'Magento categories and mappings were preserved.',
                        $treeCode
                    )
                );
            }
            if (!is_array($data['categoryTree']) || ($data['categoryTree']['code'] ?? '') !== $treeCode) {
                throw new LocalizedException(__('Ergonode returned an unexpected category tree.'));
            }
        } catch (MissingCategoryTreeException $exception) {
            $this->sourceState->record($treeId, CategoryTreeSourceState::MISSING);
            throw $exception;
        } catch (Throwable $exception) {
            $this->sourceState->record($treeId, CategoryTreeSourceState::UNAVAILABLE);
            throw $exception;
        }
        $this->sourceState->record($treeId, CategoryTreeSourceState::AVAILABLE);
    }
}
