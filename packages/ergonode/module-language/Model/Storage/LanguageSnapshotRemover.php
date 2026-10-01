<?php

declare(strict_types=1);

namespace Ergonode\Language\Model\Storage;

use Ergonode\Language\Api\LanguageSnapshotRemoverInterface;
use Ergonode\Language\Model\Mapping\MappingLock;
use Ergonode\Language\Model\Mapping\MappingCache;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

class LanguageSnapshotRemover implements LanguageSnapshotRemoverInterface
{
    private const string TABLE = 'ergonode_language';

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly MappingLock $lock,
        private readonly MappingCache $cache
    ) {
    }

    public function remove(string $languageCode): void
    {
        $languageCode = trim($languageCode);
        if ($languageCode === '') {
            throw new LocalizedException(__('Ergonode language code is required.'));
        }

        $this->lock->run(function () use ($languageCode): void {
            $deleted = $this->resourceConnection->getConnection()->delete(
                $this->resourceConnection->getTableName(self::TABLE),
                ['language_code = ?' => $languageCode]
            );
            if ($deleted !== 1) {
                throw new LocalizedException(
                    __('Language "%1" is not available in the local snapshot.', $languageCode)
                );
            }
            $this->cache->invalidate();
        });
    }
}
