<?php

declare(strict_types=1);

namespace Ergonode\Language\Model;

use Ergonode\Core\Api\GraphQlQueryClientInterface;
use Ergonode\Language\Api\ErgonodeLanguageCodesProviderInterface;
use Ergonode\Language\Api\ErgonodeLanguageCodesRefresherInterface;
use Ergonode\Language\Model\GraphQl\LanguageQuery;
use Ergonode\Language\Model\Mapping\MappingLock;
use Ergonode\Language\Model\Mapping\MappingCache;
use Ergonode\Language\Model\Storage\LanguageCodeStorage;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;

class LanguageProvider implements ErgonodeLanguageCodesProviderInterface, ErgonodeLanguageCodesRefresherInterface
{
    private const int PAGE_SIZE = 100;
    private const string REFRESH_LOCK = 'ergonode_language_refresh';

    public function __construct(
        private readonly GraphQlQueryClientInterface $client,
        private readonly LanguageCodeStorage $storage,
        private readonly MappingLock $lock,
        private readonly MappingCache $cache,
        private readonly LockManagerInterface $lockManager
    ) {
    }

    public function getErgonodeLanguageCodes(): array
    {
        return $this->storage->getCodes();
    }

    public function refreshErgonodeLanguageCodes(): array
    {
        if (!$this->lockManager->lock(self::REFRESH_LOCK, 0)) {
            throw new LocalizedException(__('Languages are already being refreshed. Please try again.'));
        }
        try {
            $languages = $this->fetchLanguageCodes();
            $this->lock->run(function () use ($languages): void {
                $this->storage->replace($languages);
                $this->cache->invalidate();
            });

            return $languages;
        } finally {
            $this->lockManager->unlock(self::REFRESH_LOCK);
        }
    }

    /**
     * @return string[]
     * @throws LocalizedException
     */
    private function fetchLanguageCodes(): array
    {
        $codes = [];
        $cursor = null;
        $seenCursors = [];

        do {
            $data = $this->client->query(
                LanguageQuery::LANGUAGE_LIST,
                ['first' => self::PAGE_SIZE, 'after' => $cursor],
                false
            );
            $list = $data['languageList'] ?? null;
            if (!is_array($list) || !isset($list['edges']) || !is_array($list['edges'])
                || !array_is_list($list['edges']) || !isset($list['pageInfo'])
                || !is_array($list['pageInfo']) || !is_bool($list['pageInfo']['hasNextPage'] ?? null)
                || !array_key_exists('endCursor', $list['pageInfo'])
                || ($list['pageInfo']['endCursor'] !== null
                    && !is_string($list['pageInfo']['endCursor']))
            ) {
                throw $this->invalidLanguageListException();
            }

            foreach ($list['edges'] as $edge) {
                if (!is_array($edge) || !isset($edge['node']) || !is_string($edge['node'])
                    || trim($edge['node']) === '' || strlen(trim($edge['node'])) > 64
                ) {
                    throw $this->invalidLanguageListException();
                }
                $codes[] = $edge['node'];
            }

            $hasNextPage = $list['pageInfo']['hasNextPage'];
            $nextCursor = $list['pageInfo']['endCursor'];
            if ($hasNextPage && (!is_string($nextCursor) || trim($nextCursor) === ''
                || isset($seenCursors[$nextCursor]) || $list['edges'] === []
            )) {
                throw $this->invalidLanguageListException();
            }

            if (!$hasNextPage) {
                break;
            }

            $seenCursors[$nextCursor] = true;
            $cursor = $nextCursor;
        } while (true);

        return $this->normalizeCodes($codes);
    }

    private function invalidLanguageListException(): LocalizedException
    {
        return new LocalizedException(__(
            'Ergonode returned an invalid language list. The snapshot was preserved.'
        ));
    }

    /**
     * @param array<int, mixed> $codes
     * @return string[]
     */
    private function normalizeCodes(array $codes): array
    {
        $normalized = [];
        foreach ($codes as $code) {
            $code = trim((string)$code);
            if ($code !== '') {
                $normalized[] = $code;
            }
        }

        return array_values(array_unique($normalized));
    }
}
