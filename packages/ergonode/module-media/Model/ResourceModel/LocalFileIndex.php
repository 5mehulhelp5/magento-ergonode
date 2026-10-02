<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\ResourceModel;

use Ergonode\Media\Model\Port\LocalFileIndexInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\Exception\LocalizedException;

class LocalFileIndex implements LocalFileIndexInterface
{
    private const string TABLE = 'ergonode_media_local_file';

    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    public function get(string $path): ?array
    {
        $connection = $this->resource->getConnection();
        $row = $connection->fetchRow($connection->select()->from($this->table())
            ->where('path_hash = ?', hash('sha256', $path, true)));
        if (!is_array($row)) {
            return null;
        }
        if ($row['path'] !== $path) {
            throw new LocalizedException(__('Local media path hash collision detected.'));
        }

        return $this->normalize($row);
    }

    public function find(string $contentHash): array
    {
        $connection = $this->resource->getConnection();

        return array_map($this->normalize(...), $connection->fetchAll($connection->select()
            ->from($this->table())->where('content_hash = ?', $contentHash)->order('path ASC')));
    }

    public function save(string $path, string $contentHash, int $size, int $modifiedAt): void
    {
        $this->get($path);
        $this->resource->getConnection()->insertOnDuplicate($this->table(), [[
            'path_hash' => hash('sha256', $path, true),
            'path' => $path,
            'content_hash' => $contentHash,
            'size' => $size,
            'modified_at' => $modifiedAt,
        ]], ['content_hash', 'size', 'modified_at']);
    }

    public function remove(string $path): void
    {
        $this->resource->getConnection()->delete($this->table(), [
            'path_hash = ?' => hash('sha256', $path, true),
            'BINARY path = ?' => $path,
        ]);
    }

    public function page(string $afterPath, int $limit): array
    {
        $connection = $this->resource->getConnection();

        return array_map($this->normalize(...), $connection->fetchAll($connection->select()->from($this->table())
            ->where('BINARY path > ?', $afterPath)
            ->order(new Expression('BINARY path ASC'))->limit(max(1, min(1000, $limit)))));
    }

    /** @param array<string, mixed> $row @return array{path:string,content_hash:string,size:int,modified_at:int} */
    private function normalize(array $row): array
    {
        return [
            'path' => (string)$row['path'],
            'content_hash' => (string)$row['content_hash'],
            'size' => (int)$row['size'],
            'modified_at' => (int)$row['modified_at'],
        ];
    }

    private function table(): string
    {
        return $this->resource->getTableName(self::TABLE);
    }
}
