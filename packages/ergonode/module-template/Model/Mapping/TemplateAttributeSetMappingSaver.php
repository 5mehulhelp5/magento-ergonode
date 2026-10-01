<?php

declare(strict_types=1);

namespace Ergonode\Template\Model\Mapping;

use Ergonode\Template\Api\MappingTargetResolverInterface;
use Ergonode\Template\Api\ProductAttributeSetProviderInterface;
use Ergonode\Template\Api\TemplateMappingSaverInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Throwable;
use Zend_Db_Expr;

class TemplateAttributeSetMappingSaver implements TemplateMappingSaverInterface
{
    /** @param MappingTargetResolverInterface[] $targetResolvers */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ProductAttributeSetProviderInterface $attributeSetProvider,
        private readonly array $targetResolvers = []
    ) {
    }

    /**
     * @param array<string, int|string|null> $mappings
     * @return array{assigned: int, unassigned: int, unchanged: int}
     * @throws LocalizedException
     */
    public function save(
        array $mappings
    ): array {
        $stats = [
            'assigned' => 0,
            'unassigned' => 0,
            'unchanged' => 0,
        ];

        $connection = $this->getConnection();
        $templateTable = $this->resourceConnection->getTableName('ergonode_template');
        $connection->beginTransaction();

        try {
            $templates = $this->loadTemplates();
            $attributeSets = $this->loadProductAttributeSetIds();
            $normalizedMappings = $this->normalizeMappings($mappings, $templates, $attributeSets);
            $changes = [];

            foreach ($templates as $templateCode => $template) {
                $currentAttributeSetId = $template['attribute_set_id'];
                $targetAttributeSetId = $normalizedMappings[$templateCode] ?? null;

                if ($currentAttributeSetId === $targetAttributeSetId) {
                    $stats['unchanged']++;
                    continue;
                }

                $changes[$templateCode] = $template + ['target_attribute_set_id' => $targetAttributeSetId];
                if ($currentAttributeSetId !== null) {
                    $connection->update(
                        $templateTable,
                        [
                            'attribute_set_id' => null,
                            'updated_at' => new Zend_Db_Expr('CURRENT_TIMESTAMP'),
                        ],
                        ['entity_id = ?' => $template['entity_id']]
                    );
                }
            }

            foreach ($changes as $templateCode => $change) {
                $targetAttributeSetId = $change['target_attribute_set_id'];
                if ($targetAttributeSetId !== null) {
                    $connection->update(
                        $templateTable,
                        [
                            'attribute_set_id' => $targetAttributeSetId,
                            'updated_at' => new Zend_Db_Expr('CURRENT_TIMESTAMP'),
                        ],
                        ['entity_id = ?' => $change['entity_id']]
                    );
                }

                if ($targetAttributeSetId === null) {
                    $stats['unassigned']++;
                } else {
                    $stats['assigned']++;
                }
            }

            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return $stats;
    }

    /**
     * @return array<string, array{entity_id: int, attribute_set_id: int|null}>
     */
    private function loadTemplates(): array
    {
        $rows = $this->getConnection()->fetchAll(
            $this->getConnection()
                ->select()
                ->from(
                    $this->resourceConnection->getTableName('ergonode_template'),
                    ['entity_id', 'code', 'attribute_set_id']
                )
                ->forUpdate(true)
        );

        $templates = [];
        foreach ($rows as $row) {
            $templates[(string)$row['code']] = [
                'entity_id' => (int)$row['entity_id'],
                'attribute_set_id' => $row['attribute_set_id'] !== null ? (int)$row['attribute_set_id'] : null,
            ];
        }

        return $templates;
    }

    /**
     * @return array<int, bool>
     */
    private function loadProductAttributeSetIds(): array
    {
        $attributeSets = [];
        foreach ($this->attributeSetProvider->getProductAttributeSets() as $attributeSet) {
            $attributeSets[$attributeSet['id']] = true;
        }

        return $attributeSets;
    }

    /**
     * @param array<string, int|string|null> $mappings
     * @param array<string, array{entity_id: int, attribute_set_id: int|null}> $templates
     * @param array<int, bool> $attributeSets
     * @return array<string, int>
     * @throws LocalizedException
     */
    private function normalizeMappings(array $mappings, array $templates, array $attributeSets): array
    {
        $normalized = [];
        $usedAttributeSetIds = [];

        foreach ($mappings as $templateCode => $attributeSetId) {
            $templateCode = trim((string)$templateCode);
            if ($templateCode === '' || $attributeSetId === null || $attributeSetId === '') {
                continue;
            }

            if (!isset($templates[$templateCode])) {
                throw new LocalizedException(__('Ergonode template "%1" is not imported yet.', $templateCode));
            }

            foreach ($this->targetResolvers as $resolver) {
                $resolvedId = $resolver->resolve($templateCode, $attributeSetId);
                if ($resolvedId !== null) {
                    $attributeSetId = $resolvedId;
                    $attributeSets = $this->loadProductAttributeSetIds();
                    break;
                }
            }
            $attributeSetId = (int)$attributeSetId;

            if ($attributeSetId <= 0 || !isset($attributeSets[$attributeSetId])) {
                throw new LocalizedException(__('Invalid Magento attribute set identifier "%1".', $attributeSetId));
            }

            if (isset($usedAttributeSetIds[$attributeSetId])) {
                throw new LocalizedException(
                    __('Magento attribute set "%1" is mapped more than once.', $attributeSetId)
                );
            }

            $normalized[$templateCode] = $attributeSetId;
            $usedAttributeSetIds[$attributeSetId] = true;
        }

        return $normalized;
    }

    private function getConnection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection();
    }
}
