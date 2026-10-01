<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\CategoryTree;

use Magento\Framework\FlagManager;
use Magento\Framework\Exception\LocalizedException;

class CategoryTreeSourceState
{
    public const string AVAILABLE = 'available';
    public const string MISSING = 'missing';
    public const string UNAVAILABLE = 'unavailable';

    public function __construct(private readonly FlagManager $flagManager)
    {
    }

    public function assertCanUseSnapshot(int $treeId): void
    {
        $state = $this->get($treeId);
        if ($state['requires_refresh'] || in_array($state['status'], [self::MISSING, self::UNAVAILABLE], true)) {
            throw new LocalizedException(__(
                'The source tree requires attention. Check it again before changing mappings. '
                . 'Magento categories were preserved.'
            ));
        }
    }

    /**
     * @return array{
     *     status: string, checked_at: string|null, snapshot_at: string|null,
     *     requires_refresh: bool, missing_codes: string[]
     * }
     */
    public function get(int $treeId): array
    {
        $data = $this->flagManager->getFlagData('ergonode_category_source_' . $treeId);

        return [
            'status' => is_array($data) ? (string)($data['status'] ?? 'unknown') : 'unknown',
            'checked_at' => is_array($data) ? ($data['checked_at'] ?? null) : null,
            'snapshot_at' => is_array($data) ? ($data['snapshot_at'] ?? null) : null,
            'requires_refresh' => is_array($data) && !empty($data['requires_refresh']),
            'missing_codes' => is_array($data) ? (array)($data['missing_codes'] ?? []) : [],
        ];
    }

    /** @param string[] $missingCodes */
    public function record(int $treeId, string $status, bool $snapshot = false, array $missingCodes = []): void
    {
        $previous = $this->get($treeId);
        $now = gmdate('Y-m-d H:i:s');
        $this->flagManager->saveFlag('ergonode_category_source_' . $treeId, [
            'status' => $status,
            'checked_at' => $now,
            'snapshot_at' => $snapshot ? $now : $previous['snapshot_at'],
            'missing_codes' => $snapshot ? $missingCodes : $previous['missing_codes'],
            'requires_refresh' => !$snapshot && ($status !== self::AVAILABLE || $previous['requires_refresh']),
        ]);
    }
}
