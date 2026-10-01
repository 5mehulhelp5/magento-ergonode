<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Model\Import;

use Ergonode\Core\Model\GraphQl\Client;
use Ergonode\TemplateConsumer\Model\GraphQl\TemplateQueries;
use Magento\Framework\Exception\LocalizedException;

class TemplateListPageReader
{
    public const int PAGE_SIZE = 50;

    public function __construct(
        private readonly Client $client
    ) {
    }

    /** @return array{codes: string[], cursor: string|null, has_more: bool} */
    public function read(?string $cursor): array
    {
        $data = $this->client->query(
            TemplateQueries::TEMPLATE_LIST,
            ['first' => self::PAGE_SIZE, 'after' => $cursor]
        );
        if (!isset($data['templateList']) || !is_array($data['templateList'])) {
            throw new LocalizedException(__('Ergonode template response is missing templateList.'));
        }

        $connection = $data['templateList'];
        if (!isset($connection['edges'])
            || !is_array($connection['edges'])
            || !isset($connection['pageInfo'])
            || !is_array($connection['pageInfo'])
            || !isset($connection['pageInfo']['hasNextPage'])
            || !is_bool($connection['pageInfo']['hasNextPage'])
        ) {
            throw new LocalizedException(__('Ergonode templateList response is incomplete.'));
        }

        $codes = [];
        foreach ($connection['edges'] as $edge) {
            $code = is_array($edge) && is_array($edge['node'] ?? null) ? ($edge['node']['code'] ?? null) : null;
            if (!is_string($code) || trim($code) === '') {
                throw new LocalizedException(__('Ergonode templateList response is incomplete.'));
            }
            $codes[] = trim($code);
        }

        $pageInfo = $connection['pageInfo'];
        $hasMore = $pageInfo['hasNextPage'];
        $next = is_string($pageInfo['endCursor'] ?? null) ? (trim($pageInfo['endCursor']) ?: null) : null;
        if ($hasMore && ($codes === [] || $next === null || $next === $cursor)) {
            throw new LocalizedException(__('Ergonode returned invalid templateList pagination.'));
        }

        return ['codes' => array_values(array_unique($codes)), 'cursor' => $next, 'has_more' => $hasMore];
    }
}
