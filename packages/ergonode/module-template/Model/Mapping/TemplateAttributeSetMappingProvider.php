<?php

declare(strict_types=1);

namespace Ergonode\Template\Model\Mapping;

use Ergonode\Template\Api\TemplateAttributeSetMappingProviderInterface;
use Ergonode\Template\Api\TemplateAttributeSetResolverInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

class TemplateAttributeSetMappingProvider implements
    TemplateAttributeSetMappingProviderInterface,
    TemplateAttributeSetResolverInterface
{
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    public function getTemplateCodesByAttributeSetIds(array $attributeSetIds): array
    {
        $attributeSetIds = array_values(array_unique(array_filter(array_map('intval', $attributeSetIds))));
        if ($attributeSetIds === []) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(
                    ['template' => $this->resourceConnection->getTableName('ergonode_template')],
                    ['attribute_set_id', 'code']
                )
                ->where('template.attribute_set_id IN (?)', $attributeSetIds)
                ->where('template.is_deleted = ?', 0)
                ->order('template.code ASC')
        );
        $result = [];
        foreach ($rows as $row) {
            $attributeSetId = (int)$row['attribute_set_id'];
            $templateCode = trim((string)$row['code']);
            if ($attributeSetId <= 0 || $templateCode === '') {
                continue;
            }
            if (isset($result[$attributeSetId]) && $result[$attributeSetId] !== $templateCode) {
                throw new LocalizedException(__(
                    'Magento attribute set ID "%1" has more than one Ergonode template mapping.',
                    $attributeSetId
                ));
            }
            $result[$attributeSetId] = $templateCode;
        }
        ksort($result);

        return $result;
    }

    public function resolveAttributeSetId(string $templateCode): ?int
    {
        $templateCode = trim($templateCode);
        if ($templateCode === '') {
            return null;
        }

        $attributeSetId = $this->resourceConnection->getConnection()->fetchOne(
            $this->resourceConnection->getConnection()
                ->select()
                ->from(
                    ['template' => $this->resourceConnection->getTableName('ergonode_template')],
                    ['attribute_set_id']
                )
                ->where('template.code = ?', $templateCode)
                ->where('template.is_deleted = ?', 0)
                ->where('template.attribute_set_id IS NOT NULL')
                ->limit(1)
        );

        return $attributeSetId === false ? null : (int)$attributeSetId;
    }
}
