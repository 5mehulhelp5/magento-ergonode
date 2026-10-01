<?php

declare(strict_types=1);

namespace Ergonode\Language\Model\Storage;

use Magento\Framework\App\ResourceConnection;
use Throwable;

class LanguageCodeStorage
{
    private const string TABLE = 'ergonode_language';

    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /** @return string[] */
    public function getCodes(): array
    {
        $connection = $this->resourceConnection->getConnection();

        return array_values(array_map(
            'strval',
            $connection->fetchCol(
                $connection->select()
                    ->from($this->resourceConnection->getTableName(self::TABLE), ['language_code'])
                    ->order('language_code ASC')
            )
        ));
    }

    /**
     * @param string[] $codes
     * @throws Throwable
     */
    public function replace(array $codes): void
    {
        $codes = array_values(array_unique(array_filter(array_map(
            static fn (mixed $code): string => trim((string)$code),
            $codes
        ))));
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);

        $connection->beginTransaction();
        try {
            $connection->delete($table);
            if ($codes !== []) {
                $connection->insertArray(
                    $table,
                    ['language_code'],
                    array_map(static fn (string $code): array => [$code], $codes)
                );
            }
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }
}
