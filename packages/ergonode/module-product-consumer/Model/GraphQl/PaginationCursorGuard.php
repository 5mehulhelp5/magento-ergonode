<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\GraphQl;

use Magento\Framework\Exception\LocalizedException;

class PaginationCursorGuard
{
    /** @var array<string, true> */
    private array $seen = [];

    private int $pages = 0;

    public function __construct(private readonly string $context)
    {
    }

    /** @param array<string, mixed> $connection */
    public function next(array $connection): ?string
    {
        $pageInfo = is_array($connection['pageInfo'] ?? null) ? $connection['pageInfo'] : [];
        if (empty($pageInfo['hasNextPage'])) {
            return null;
        }
        $next = trim((string)($pageInfo['endCursor'] ?? ''));
        $this->pages++;
        if ($next === '' || isset($this->seen[$next]) || $this->pages > 100) {
            throw new LocalizedException(__('Ergonode returned invalid pagination for %1.', $this->context));
        }
        $this->seen[$next] = true;

        return $next;
    }
}
