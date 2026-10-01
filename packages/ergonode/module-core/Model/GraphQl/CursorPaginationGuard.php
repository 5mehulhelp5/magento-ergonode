<?php

declare(strict_types=1);

namespace Ergonode\Core\Model\GraphQl;

use Ergonode\Core\Api\CursorPaginationGuardInterface;
use Magento\Framework\Exception\LocalizedException;

class CursorPaginationGuard implements CursorPaginationGuardInterface
{
    /** @var array<string, true> */
    private array $seenCursors = [];

    private int $pageCount = 0;

    public function __construct(private readonly string $context)
    {
    }

    public function next(array $pageInfo): ?string
    {
        ++$this->pageCount;
        if (empty($pageInfo['hasNextPage'])) {
            return null;
        }
        if ($this->pageCount >= self::MAX_PAGES) {
            throw new LocalizedException(__(
                'Ergonode pagination for %1 exceeded the %2-page limit.',
                $this->context,
                self::MAX_PAGES
            ));
        }

        $next = trim((string)($pageInfo['endCursor'] ?? ''));
        if ($next === '' || isset($this->seenCursors[$next])) {
            throw new LocalizedException(__(
                'Ergonode returned invalid pagination or a cyclic cursor for %1.',
                $this->context
            ));
        }
        $this->seenCursors[$next] = true;

        return $next;
    }
}
