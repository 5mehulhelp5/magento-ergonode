<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Model\ManualTree;

use Magento\Framework\Exception\LocalizedException;

class PendingCategoryPublisher
{
    public function __construct(
        private readonly CategoryBatchPublisher $batchPublisher,
        private readonly CategoryPublicationCheckpoint $checkpoint
    ) {
    }

    /** @param array<int, array<string, mixed>> $items */
    public function publish(int $categoryTreeId, array $items): void
    {
        $completed = $this->checkpoint->get($categoryTreeId, $items, 'layout');
        $pending = [];
        foreach ($items as $item) {
            $extension = is_array($item['extension_data']['to_ergonode'] ?? null)
                ? $item['extension_data']['to_ergonode'] : [];
            if (!empty($extension['pending_create']) && empty($extension['remote_prepared'])) {
                $pending[] = $item;
            }
        }
        foreach (array_chunk($this->parentFirst($pending), CategoryBatchPublisher::MAX_BATCH_SIZE) as $batch) {
            $batch = array_values(array_filter(
                $batch,
                static fn (array $item): bool => !isset($completed[trim((string)($item['code'] ?? ''))])
            ));
            if ($batch === []) {
                continue;
            }
            $results = $this->batchPublisher->publish($categoryTreeId, $batch);
            $errors = [];
            foreach ($results as $result) {
                if (in_array($result['status'], ['synchronized', 'verified'], true)) {
                    $completed[$result['code']] = true;
                } else {
                    $errors[] = $result['message'];
                }
            }
            $this->checkpoint->save($categoryTreeId, $items, array_keys($completed), 'layout');
            if ($errors !== []) {
                throw new LocalizedException(__(implode(' ', array_unique($errors))));
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function parentFirst(array $items): array
    {
        $byCode = [];
        foreach ($items as $item) {
            $code = trim((string)($item['code'] ?? ''));
            if ($code === '' || isset($byCode[$code])) {
                throw new LocalizedException(__('Category codes must be non-empty and unique.'));
            }
            $byCode[$code] = $item;
        }
        $children = [];
        $ready = [];
        foreach ($byCode as $code => $item) {
            $parent = trim((string)($item['parent_code'] ?? ''));
            if (isset($byCode[$parent])) {
                $children[$parent][] = $code;
            } else {
                $ready[] = $code;
            }
        }
        $ordered = [];
        for ($index = 0; isset($ready[$index]); $index++) {
            $code = $ready[$index];
            $ordered[] = $byCode[$code];
            foreach ($children[$code] ?? [] as $child) {
                $ready[] = $child;
            }
        }
        if (count($ordered) !== count($items)) {
            throw new LocalizedException(__('Category tree contains a cycle.'));
        }
        return $ordered;
    }

    /** @param array<int, array<string, mixed>> $items */
    public function complete(int $categoryTreeId, array $items): void
    {
        $this->checkpoint->clear($categoryTreeId, $items, 'layout');
    }
}
