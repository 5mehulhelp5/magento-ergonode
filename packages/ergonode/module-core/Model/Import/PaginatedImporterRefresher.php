<?php

declare(strict_types=1);

namespace Ergonode\Core\Model\Import;

use Magento\Framework\Exception\LocalizedException;
use Ergonode\Core\Api\PaginatedImporterRefresherInterface;

class PaginatedImporterRefresher implements PaginatedImporterRefresherInterface
{
    private const int MAX_PAGES = 1000;

    public function refresh(callable $importPage): void
    {
        $cursor = null;
        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $result = $importPage($cursor);
            if (empty($result['has_more'])) {
                return;
            }

            $nextCursor = isset($result['cursor']) ? trim((string)$result['cursor']) : '';
            if ($nextCursor === '' || $nextCursor === $cursor) {
                throw new LocalizedException(__('Ergonode cache refresh pagination did not advance.'));
            }
            $cursor = $nextCursor;
        }

        throw new LocalizedException(__('Ergonode cache refresh exceeded the page limit.'));
    }
}
