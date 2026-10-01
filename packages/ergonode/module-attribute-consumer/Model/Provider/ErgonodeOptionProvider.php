<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Model\Provider;

use Ergonode\AttributeConsumer\Api\ErgonodeOptionProviderInterface;
use Ergonode\Core\Api\MappingVisibilityProviderInterface;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use InvalidArgumentException;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\Serializer\Json;

class ErgonodeOptionProvider implements ErgonodeOptionProviderInterface
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly Json $json,
        private readonly LanguageStoreMappingProviderInterface $languageMappingProvider,
        private readonly MappingVisibilityProviderInterface $visibilityProvider,
        private readonly OptionSnapshotCache $snapshotCache
    ) {
    }

    /**
     * @return array<int, array{label: string, code: string, scope: string, type: string, active: bool}>
     */
    public function getOptions(string $attributeCode): array
    {
        if (isset($this->snapshotCache->options[$attributeCode])) {
            return $this->snapshotCache->options[$attributeCode];
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('ergonode_attribute_option');
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($table, ['option_code', 'labels_json'])
                ->where('attribute_code = ?', $attributeCode)
                ->order('option_code ASC')
        );
        $options = [];
        $activeMap = $this->visibilityProvider->getActiveMap(
            'option',
            'ergo',
            array_map(static fn (array $row): string => (string)$row['option_code'], $rows),
            $attributeCode
        );

        foreach ($rows as $row) {
            $code = (string)$row['option_code'];
            $options[] = [
                'label' => $this->resolveLabel((string)$row['labels_json'], $code),
                'code' => $code,
                'scope' => $this->languageMappingProvider->getAdminLanguageCode() ?? '',
                'type' => 'option',
                'active' => $activeMap[$code] ?? true,
            ];
        }

        $candidate = [$attributeCode => $options];
        $this->snapshotCache->options = $this->snapshotCache->canRetain($candidate) ? $candidate : [];
        return $options;
    }

    /**
     * @return array<int, array{code: string, labels: array<string, string>}>
     */
    public function getOptionDefinitions(string $attributeCode): array
    {
        return $this->getOptionDefinitionsByCodes([$attributeCode])[$attributeCode];
    }

    public function getOptionDefinitionsByCodes(array $attributeCodes): array
    {
        $codes = array_values(array_unique($attributeCodes));
        if ($codes === []) {
            return [];
        }
        if (array_diff($codes, array_keys($this->snapshotCache->definitions)) === []) {
            return array_intersect_key($this->snapshotCache->definitions, array_fill_keys($codes, true));
        }
        $definitions = array_fill_keys($codes, []);
        foreach ($this->iterateOptionDefinitions($codes) as $row) {
            $definitions[$row['attribute_code']][] = [
                'code' => $row['code'], 'labels' => $row['labels'], 'sort_order' => $row['sort_order'],
            ];
        }
        foreach ($definitions as &$options) {
            usort($options, static fn (array $left, array $right): int =>
                ($left['sort_order'] <=> $right['sort_order']) ?: strcmp($left['code'], $right['code']));
            foreach ($options as &$option) {
                unset($option['sort_order']);
            }
            unset($option);
        }
        unset($options);
        $this->snapshotCache->definitions = $this->snapshotCache->canRetain($definitions) ? $definitions : [];
        return $definitions;
    }

    public function iterateOptionDefinitions(array $attributeCodes): iterable
    {
        $connection = $this->resourceConnection->getConnection();
        foreach (array_chunk(array_values(array_unique($attributeCodes)), 200) as $chunk) {
            $after = 0;
            do {
                $rows = $connection->fetchAll(
                    $connection->select()
                        ->from(
                            $this->resourceConnection->getTableName('ergonode_attribute_option'),
                            ['entity_id', 'attribute_code', 'option_code', 'sort_order', 'labels_json']
                        )
                        ->where('attribute_code IN (?)', $chunk)
                        ->where('entity_id > ?', $after)
                        ->order('entity_id ASC')
                        ->limit(200)
                );
                foreach ($rows as $row) {
                    $after = (int)$row['entity_id'];
                    yield [
                        'attribute_code' => (string)$row['attribute_code'],
                        'code' => (string)$row['option_code'],
                        'sort_order' => (int)$row['sort_order'],
                        'labels' => $this->decodeLabels((string)$row['labels_json']),
                    ];
                }
            } while (count($rows) === 200);
        }
    }

    private function resolveLabel(string $labelsJson, string $fallback): string
    {
        $labels = $this->decodeLabels($labelsJson);

        if ($labels === []) {
            return $fallback;
        }

        $defaultLocale = $this->languageMappingProvider->getAdminLanguageCode();

        return (string)(($defaultLocale !== null ? $labels[$defaultLocale] ?? null : null)
            ?? reset($labels)
            ?: $fallback);
    }

    /**
     * @return array<string, string>
     */
    private function decodeLabels(string $labelsJson): array
    {
        try {
            $labels = $this->json->unserialize($labelsJson);
        } catch (InvalidArgumentException) {
            return [];
        }

        if (!is_array($labels)) {
            return [];
        }

        $result = [];
        foreach ($labels as $language => $label) {
            $language = trim((string)$language);
            $label = trim((string)$label);
            if ($language !== '' && $label !== '') {
                $result[$language] = $label;
            }
        }

        return $result;
    }
    /**
     * @param  string[] $attributeCodes
     * @return array<string, int>
     */
    public function getOptionCounts(array $attributeCodes): array
    {
        if ($attributeCodes === []) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $counts = $connection->fetchPairs(
            $connection->select()
                ->from(
                    $this->resourceConnection->getTableName('ergonode_attribute_option'),
                    ['attribute_code', 'option_count' => 'COUNT(*)']
                )
                ->where('attribute_code IN (?)', array_values(array_unique($attributeCodes)))
                ->group('attribute_code')
        );

        return array_map('intval', $counts);
    }
}
