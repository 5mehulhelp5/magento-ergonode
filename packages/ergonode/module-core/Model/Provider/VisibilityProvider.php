<?php

declare(strict_types=1);

namespace Ergonode\Core\Model\Provider;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Ergonode\Core\Api\MappingVisibilityProviderInterface;
use Ergonode\Core\Api\MappingVisibilitySaverInterface;

class VisibilityProvider implements MappingVisibilityProviderInterface, MappingVisibilitySaverInterface
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * @param string[] $identifiers
     * @return array<string, bool>
     */
    public function getActiveMap(
        string $entityType,
        string $source,
        array $identifiers,
        string $parentIdentifier = ''
    ): array {
        $identifiers = array_values(array_unique(array_filter(array_map(
            static fn (string $identifier): string => trim($identifier),
            $identifiers
        ), static fn (string $identifier): bool => $identifier !== '')));

        if (!$identifiers) {
            return [];
        }

        $connection = $this->getConnection();
        $table = $this->resourceConnection->getTableName('ergonode_mapping_visibility');
        $activeMap = array_fill_keys($identifiers, true);
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($table, ['identifier', 'is_active'])
                ->where('entity_type = ?', $entityType)
                ->where('source = ?', $source)
                ->where('parent_identifier = ?', $parentIdentifier)
                ->where('identifier IN (?)', $identifiers)
        );

        foreach ($rows as $row) {
            $activeMap[(string)$row['identifier']] = (bool)$row['is_active'];
        }

        return $activeMap;
    }

    /**
     * @param array<int, array{
     *     entity_type: string,
     *     source: string,
     *     parent_identifier?: string,
     *     identifier: string,
     *     active: bool
     * }> $items
     */
    public function saveMany(array $items): void
    {
        $connection = $this->getConnection();
        $table = $this->resourceConnection->getTableName('ergonode_mapping_visibility');
        $normalized = [];

        foreach ($items as $item) {
            $entityType = (string)($item['entity_type'] ?? '');
            $source = (string)($item['source'] ?? '');
            $parentIdentifier = (string)($item['parent_identifier'] ?? '');
            $identifier = (string)($item['identifier'] ?? '');
            $isActive = !empty($item['active']) ? 1 : 0;

            if ($entityType === '' || $source === '' || $identifier === '') {
                continue;
            }

            $key = $this->visibilityKey($entityType, $source, $parentIdentifier, $identifier);
            $normalized[$key] = [
                'entity_type' => $entityType,
                'source' => $source,
                'parent_identifier' => $parentIdentifier,
                'identifier' => $identifier,
                'is_active' => $isActive,
            ];
        }

        if (!$normalized) {
            return;
        }

        $existing = [];
        foreach ($this->groupByScope($normalized) as $group) {
            $rows = $connection->fetchAll(
                $connection->select()
                    ->from(
                        $table,
                        ['entity_id', 'entity_type', 'source', 'parent_identifier', 'identifier', 'is_active']
                    )
                    ->where('entity_type = ?', $group['entity_type'])
                    ->where('source = ?', $group['source'])
                    ->where('parent_identifier = ?', $group['parent_identifier'])
                    ->where('identifier IN (?)', $group['identifiers'])
            );

            foreach ($rows as $row) {
                $existing[$this->visibilityKey(
                    (string)$row['entity_type'],
                    (string)$row['source'],
                    (string)$row['parent_identifier'],
                    (string)$row['identifier']
                )] = $row;
            }
        }

        $insertRows = [];
        foreach ($normalized as $key => $item) {
            if (!isset($existing[$key])) {
                $insertRows[] = $item;
                continue;
            }

            if ((int)$existing[$key]['is_active'] !== (int)$item['is_active']) {
                $connection->update(
                    $table,
                    ['is_active' => (int)$item['is_active']],
                    ['entity_id = ?' => (int)$existing[$key]['entity_id']]
                );
            }
        }

        if ($insertRows) {
            $connection->insertMultiple($table, $insertRows);
        }
    }

    public function deleteByParentIdentifier(string $entityType, string $source, string $parentIdentifier): void
    {
        $entityType = trim($entityType);
        $source = trim($source);
        $parentIdentifier = trim($parentIdentifier);

        if ($entityType === '' || $source === '' || $parentIdentifier === '') {
            return;
        }

        $this->getConnection()->delete(
            $this->resourceConnection->getTableName('ergonode_mapping_visibility'),
            [
                'entity_type = ?' => $entityType,
                'source = ?' => $source,
                'parent_identifier = ?' => $parentIdentifier,
            ]
        );
    }

    private function getConnection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection();
    }

    private function visibilityKey(
        string $entityType,
        string $source,
        string $parentIdentifier,
        string $identifier
    ): string {
        return implode("\n", [$entityType, $source, $parentIdentifier, $identifier]);
    }

    /**
     * @param array<string, array<string, mixed>> $items
     * @return array<int, array{entity_type: string, source: string, parent_identifier: string, identifiers: string[]}>
     */
    private function groupByScope(array $items): array
    {
        $groups = [];

        foreach ($items as $item) {
            $scopeKey = $this->visibilityKey(
                (string)$item['entity_type'],
                (string)$item['source'],
                (string)$item['parent_identifier'],
                ''
            );
            if (!isset($groups[$scopeKey])) {
                $groups[$scopeKey] = [
                    'entity_type' => (string)$item['entity_type'],
                    'source' => (string)$item['source'],
                    'parent_identifier' => (string)$item['parent_identifier'],
                    'identifiers' => [],
                ];
            }

            $groups[$scopeKey]['identifiers'][] = (string)$item['identifier'];
        }

        return array_values($groups);
    }
}
