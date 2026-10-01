<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Config\Source;

use InvalidArgumentException;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Serialize\Serializer\Json;

class CategoryTreeOptions implements OptionSourceInterface
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly Json $json
    ) {
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [
            [
                'value' => '',
                'label' => (string)__('Choose category tree'),
            ],
        ];
        foreach ($this->loadRows() as $row) {
            $code = (string)$row['code'];
            if ($code === '') {
                continue;
            }

            $options[] = [
                'value' => $code,
                'label' => $this->resolveLabel((string)$row['labels_json'], $code),
            ];
        }

        return $options;
    }

    /**
     * @return array<int, array{code: string, labels_json: string}>
     */
    private function loadRows(): array
    {
        $connection = $this->resourceConnection->getConnection();

        return $connection->fetchAll(
            $connection->select()
                ->from(
                    $this->resourceConnection->getTableName('ergonode_category_tree_option'),
                    ['code', 'labels_json']
                )
                ->order('code ASC')
        );
    }

    private function resolveLabel(string $labelsJson, string $fallback): string
    {
        try {
            $labels = $this->json->unserialize($labelsJson);
        } catch (InvalidArgumentException) {
            $labels = [];
        }

        if (!is_array($labels)) {
            return $fallback;
        }

        return (string)(reset($labels) ?: $fallback);
    }
}
