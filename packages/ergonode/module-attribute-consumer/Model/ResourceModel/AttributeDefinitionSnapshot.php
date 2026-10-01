<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Model\ResourceModel;

use Ergonode\AttributeConsumer\Api\AttributeAvailabilityInterface;
use Ergonode\AttributeConsumer\Model\Import\AttributeCacheWriter;
use Ergonode\AttributeConsumer\Model\Port\AttributeDefinitionSnapshotInterface;
use Ergonode\AttributeConsumer\Model\Provider\OptionSnapshotCache;
use Ergonode\AttributeConsumer\Model\Provider\ErgonodeAttributeProvider;
use Ergonode\Core\Model\Import\CursorStorage;
use InvalidArgumentException;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Throwable;

class AttributeDefinitionSnapshot implements AttributeAvailabilityInterface, AttributeDefinitionSnapshotInterface
{
    public const string PROCESS_CODE = 'attribute_definition_snapshot';

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly AttributeCacheWriter $writer,
        private readonly CursorStorage $cursors,
        private readonly Json $json,
        private readonly OptionSnapshotCache $snapshotCache,
        private readonly ErgonodeAttributeProvider $attributeProvider
    ) {
    }

    /** @return array{source: string, attribute: ?string, deleted: ?string}|null */
    public function getState(): ?array
    {
        $cursor = $this->cursors->get(self::PROCESS_CODE)['cursor'] ?? null;

        return $cursor === null ? null : $this->json->unserialize($cursor);
    }

    public function getCodes(): array
    {
        return array_map('strval', $this->resource->getConnection()->fetchCol(
            $this->resource->getConnection()->select()
                ->from($this->resource->getTableName('ergonode_attribute'), ['code'])
        ));
    }

    /**
     * @param iterable<int, array{
     *     code: string, type: string, scope: string, labels: array<string, string>,
     *     parameters: array<string, bool|string>, hash: string
     * }> $attributes
     * @param array{source: string, attribute: ?string, deleted: ?string} $state
     * @return string[] Removed definition codes. Mappings and Magento EAV are preserved.
     */
    public function replace(iterable $attributes, array $state): array
    {
        $connection = $this->resource->getConnection();
        $this->snapshotCache->reset();
        $connection->beginTransaction();
        try {
            $codes = [];
            $batch = [];
            foreach ($attributes as $attribute) {
                $codes[$attribute['code']] = true;
                $batch[$attribute['code']] = $attribute;
                if (count($batch) >= 200) {
                    $this->writer->saveAttributes(array_values($batch));
                    $batch = [];
                }
            }
            $this->writer->saveAttributes(array_values($batch));
            $removed = array_values(array_diff($this->getCodes(), array_keys($codes)));
            if ($removed !== []) {
                $connection->delete(
                    $this->resource->getTableName('ergonode_attribute_option'),
                    ['attribute_code IN (?)' => $removed]
                );
                $connection->delete($this->resource->getTableName('ergonode_attribute'), ['code IN (?)' => $removed]);
            }
            $this->cursors->save(self::PROCESS_CODE, $this->json->serialize($state));
            $connection->commit();
            $this->attributeProvider->reset();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return $removed;
    }
    /**
     * @return array{has_more: bool, cursor: ?string, page_size: int, imported: int, changed: int,
     *     unchanged: int, attribute_codes: list<string>}
     */
    public function page(?string $cursor, ?int $pageSize): array
    {
        $size = $pageSize ?? 200;
        if ($size < 1 || $size > 200) {
            throw new LocalizedException(__('Snapshot page size must be between 1 and 200.'));
        }
        $revision = hash('sha256', $this->json->serialize($this->getState()));
        $after = null;
        if ($cursor !== null) {
            try {
                $token = $this->json->unserialize((string)base64_decode($cursor, true));
            } catch (InvalidArgumentException) {
                $token = null;
            }
            if (!is_array($token)
                || ($token['revision'] ?? null) !== $revision
                || !is_string($token['after'] ?? null)
            ) {
                throw new LocalizedException(__('The attribute snapshot changed. Restart the refresh.'));
            }
            $after = $token['after'];
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()->from($this->resource->getTableName('ergonode_attribute'), ['code'])
            ->where('type <> ?', 'gallery')->order('code ASC')->limit($size + 1);
        if ($after !== null) {
            $select->where('code > ?', $after);
        }
        $codes = array_map('strval', $connection->fetchCol($select));
        $hasMore = count($codes) > $size;
        if ($hasMore) {
            array_pop($codes);
        }
        return [
            'has_more' => $hasMore,
            'cursor' => $hasMore
                ? base64_encode($this->json->serialize(['revision' => $revision, 'after' => end($codes)]))
                : null,
            'page_size' => $size, 'imported' => 0, 'changed' => 0, 'unchanged' => count($codes),
            'attribute_codes' => $codes,
        ];
    }
}
