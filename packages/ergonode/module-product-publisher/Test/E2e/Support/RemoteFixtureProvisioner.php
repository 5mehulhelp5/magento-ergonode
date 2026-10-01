<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\E2e\Support;

use Ergonode\TemplatePublisher\Api\TemplateCreatorInterface;
use Magento\Framework\App\ResourceConnection;
use RuntimeException;
use Throwable;

class RemoteFixtureProvisioner
{
    /** @var array<string, array<string, string>> */
    private array $created = [];

    public function __construct(
        private readonly UserRestClient $client,
        private readonly TemplateCreatorInterface $templateCreator,
        private readonly ResourceConnection $resourceConnection,
        private readonly string $languageCode
    ) {
    }

    /** @param array<string, mixed> $manifest @param string[] $cohorts */
    public function provisionCategoriesAndTrees(array $manifest, array $cohorts): void
    {
        foreach ((array)$manifest['categories'] as $category) {
            if (in_array((string)$category['cohort'], $cohorts, true)) {
                $this->ensureNamedEntity(
                    'categories',
                    (string)$category['remote_code'],
                    (string)$category['name']
                );
            }
        }
        foreach ((array)$manifest['roots'] as $root) {
            if (!in_array((string)$root['cohort'], $cohorts, true)) {
                continue;
            }
            $tree = $this->ensureNamedEntity('trees', (string)$root['tree_code'], (string)$root['name']);
            $items = $this->treeItems($manifest, (int)$root['root_category_id'], $cohorts);
            $this->client->put('trees/' . rawurlencode((string)$tree['id']), [
                'name' => [$this->languageCode => (string)$root['name']],
                'categories' => $items,
            ]);
        }
    }

    /** @param array<string, mixed> $manifest @param string[] $cohorts */
    public function provisionTemplates(array $manifest, array $cohorts): void
    {
        foreach ((array)$manifest['templates'] as $template) {
            if (!in_array((string)$template['cohort'], $cohorts, true)) {
                continue;
            }
            $code = (string)$template['template_code'];
            $entity = $this->client->findByCode('templates', $code);
            if ($entity === null) {
                $this->created['templates'][$code] = '';
                $this->templateCreator->create($code, [
                    $this->languageCode => (string)$template['name'],
                ]);
                $entity = $this->requireEntity('templates', $code);
                $this->created['templates'][$code] = (string)$entity['id'];
            }
            $this->client->put('templates/' . rawurlencode((string)$entity['id']), [
                'name' => (string)$template['name'],
                'code' => $code,
                'elements' => $this->templateElements((int)$template['attribute_set_id']),
            ]);
        }
    }

    /** @param array<string, mixed> $manifest */
    public function removeMappedOnlyAttributes(array $manifest): void
    {
        foreach ((array)$manifest['attributes'] as $attribute) {
            if ((string)$attribute['cohort'] === CohortPlanner::MAPPED_ONLY) {
                $this->deleteCreated('attributes', (string)$attribute['remote_code']);
            }
        }
    }

    /** @param array<string, mixed> $manifest */
    public function removeMappedOnlyCategoriesAndTrees(array $manifest): void
    {
        foreach ((array)$manifest['roots'] as $root) {
            if ((string)$root['cohort'] === CohortPlanner::MAPPED_ONLY) {
                $this->deleteCreated('trees', (string)$root['tree_code']);
            }
        }
        foreach ((array)$manifest['categories'] as $category) {
            if ((string)$category['cohort'] === CohortPlanner::MAPPED_ONLY) {
                $this->deleteCreated('categories', (string)$category['remote_code']);
            }
        }
    }

    /** @param array<string, mixed> $manifest */
    public function removeMappedOnlyTemplates(array $manifest): void
    {
        foreach ((array)$manifest['templates'] as $template) {
            if ((string)$template['cohort'] === CohortPlanner::MAPPED_ONLY) {
                $this->deleteCreated('templates', (string)$template['template_code']);
            }
        }
    }

    public function registerCreatedAttribute(string $code): void
    {
        $entity = $this->client->findByCode('attributes', $code);
        if ($entity !== null) {
            $this->created['attributes'][$code] = (string)$entity['id'];
        }
    }

    /** @param string[] $codes */
    public function expectCreatedAttributes(array $codes): void
    {
        foreach ($codes as $code) {
            $code = trim($code);
            if ($code !== '') {
                $this->created['attributes'][$code] = '';
            }
        }
    }

    public function registerCreatedProduct(string $sku): void
    {
        $entity = $this->client->findByField('products', 'sku', $sku);
        if ($entity !== null) {
            $this->created['products'][$sku] = (string)$entity['id'];
        }
    }

    public function cleanup(): void
    {
        $failure = null;
        foreach (['products', 'templates', 'trees', 'categories', 'attributes'] as $resource) {
            foreach (array_reverse($this->created[$resource] ?? [], true) as $code => $id) {
                try {
                    if ($id === '') {
                        $field = $resource === 'products' ? 'sku' : 'code';
                        $entity = $this->client->findByField($resource, $field, $code);
                        $id = is_array($entity) ? trim((string)($entity['id'] ?? '')) : '';
                    }
                    if ($id === '') {
                        continue;
                    }
                    $this->client->delete($resource . '/' . rawurlencode($id));
                } catch (Throwable $exception) {
                    if (!str_contains($exception->getMessage(), 'HTTP 404')) {
                        $failure ??= $exception;
                    }
                }
                unset($this->created[$resource][$code]);
            }
        }
        if ($failure !== null) {
            throw new RuntimeException('Unable to remove every Ergonode E2E fixture.', 0, $failure);
        }
    }

    /** @return array<string, mixed> */
    private function ensureNamedEntity(string $resource, string $code, string $name): array
    {
        $entity = $this->client->findByCode($resource, $code);
        if ($entity !== null) {
            return $entity;
        }
        $this->created[$resource][$code] = '';
        try {
            $this->client->post($resource, [
                'code' => $code,
                'name' => [$this->languageCode => $name],
            ]);
        } catch (RuntimeException $localizedNameException) {
            $entity = $this->client->findByCode($resource, $code);
            if ($entity === null) {
                try {
                    $this->client->post($resource, ['code' => $code, 'name' => $name]);
                } catch (RuntimeException $scalarNameException) {
                    $entity = $this->client->findByCode($resource, $code);
                    if ($entity === null) {
                        throw new RuntimeException(
                            $scalarNameException->getMessage(),
                            0,
                            $localizedNameException
                        );
                    }
                }
            }
        }
        $entity = $this->requireEntity($resource, $code);
        $this->created[$resource][$code] = (string)$entity['id'];

        return $entity;
    }

    /** @return array<string, mixed> */
    private function requireEntity(string $resource, string $code): array
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $entity = $this->client->findByCode($resource, $code);
            if ($entity !== null) {
                return $entity;
            }
            usleep(250000);
        }

        throw new RuntimeException(sprintf('Ergonode %s "%s" did not become visible in REST.', $resource, $code));
    }

    private function deleteCreated(string $resource, string $code): void
    {
        $id = $this->created[$resource][$code] ?? null;
        if ($id === null) {
            return;
        }
        if ($id === '') {
            $entity = $this->client->findByCode($resource, $code);
            $id = is_array($entity) ? trim((string)($entity['id'] ?? '')) : '';
        }
        if ($id === '') {
            unset($this->created[$resource][$code]);
            return;
        }
        $this->client->delete($resource . '/' . rawurlencode($id));
        unset($this->created[$resource][$code]);
    }

    /** @param array<string, mixed> $manifest @param string[] $cohorts @return array<int, array<string, mixed>> */
    private function treeItems(array $manifest, int $rootCategoryId, array $cohorts): array
    {
        $categories = [];
        $idsByMagentoId = [];
        foreach ((array)$manifest['categories'] as $category) {
            if ((int)$category['root_category_id'] !== $rootCategoryId
                || !in_array((string)$category['cohort'], $cohorts, true)
            ) {
                continue;
            }
            $entity = $this->requireEntity('categories', (string)$category['remote_code']);
            $id = (int)$category['magento_category_id'];
            $categories[$id] = $category;
            $idsByMagentoId[$id] = (string)$entity['id'];
        }
        $children = [];
        foreach ($categories as $id => $category) {
            $parentId = (int)$category['parent_id'];
            $children[isset($categories[$parentId]) ? $parentId : 0][] = $id;
        }

        return $this->nestedTreeItems(0, $children, $idsByMagentoId);
    }

    /**
     * @param array<int, int[]> $children
     * @param array<int, string> $idsByMagentoId
     * @return array<int, array{category_id: string, children: array}>
     */
    private function nestedTreeItems(int $parentId, array $children, array $idsByMagentoId): array
    {
        $result = [];
        foreach ($children[$parentId] ?? [] as $categoryId) {
            $result[] = [
                'category_id' => $idsByMagentoId[$categoryId],
                'children' => $this->nestedTreeItems($categoryId, $children, $idsByMagentoId),
            ];
        }

        return $result;
    }

    /** @return array<int, array<string, mixed>> */
    private function templateElements(int $attributeSetId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(
                    ['assignment' => $this->resourceConnection->getTableName('eav_entity_attribute')],
                    ['sort_order']
                )
                ->join(
                    ['attribute' => $this->resourceConnection->getTableName('eav_attribute')],
                    'attribute.attribute_id = assignment.attribute_id',
                    ['attribute_code']
                )
                ->join(
                    ['mapping' => $this->resourceConnection->getTableName('ergonode_product_attribute_mapping')],
                    "mapping.magento_attribute_code = attribute.attribute_code AND mapping.status = 'complete'",
                    ['ergonode_attribute_code', 'ergonode_type']
                )
                ->join(
                    ['attribute_group' => $this->resourceConnection->getTableName('eav_attribute_group')],
                    'attribute_group.attribute_group_id = assignment.attribute_group_id',
                    ['attribute_group_name', 'group_sort_order' => 'sort_order']
                )
                ->where('assignment.attribute_set_id = ?', $attributeSetId)
                ->order('attribute_group.sort_order ASC')
                ->order('assignment.sort_order ASC')
        );
        $groups = [];
        foreach ($rows as $row) {
            $entity = $this->client->findByCode('attributes', (string)$row['ergonode_attribute_code']);
            if ($entity === null) {
                continue;
            }
            $groups[(string)$row['attribute_group_name']][] = [
                'id' => (string)$entity['id'],
                'type' => $this->layoutType((string)($entity['type'] ?? $row['ergonode_type'])),
            ];
        }
        $elements = [];
        $row = 0;
        foreach ($groups as $groupName => $attributes) {
            $height = max(2, (int)ceil(count($attributes) / 4) + 1);
            $elements[] = $this->layoutElement('SECTION', $row, 0, 4, $height, ['label' => $groupName]);
            foreach ($attributes as $index => $attribute) {
                $elements[] = $this->layoutElement(
                    $attribute['type'],
                    $row + 1 + intdiv($index, 4),
                    $index % 4,
                    1,
                    1,
                    ['attribute_id' => $attribute['id'], 'required' => false]
                );
            }
            $row += $height;
        }
        if ($elements === []) {
            throw new RuntimeException('A live E2E template cannot be created without mapped attributes.');
        }

        return $elements;
    }

    /** @param array<string, mixed> $properties @return array<string, mixed> */
    private function layoutElement(
        string $type,
        int $row,
        int $column,
        int $width,
        int $height,
        array $properties
    ): array {
        return [
            'position' => ['x' => $column, 'y' => $row],
            'size' => ['width' => $width, 'height' => $height],
            'properties' => $properties,
            'type' => $type,
        ];
    }

    private function layoutType(string $type): string
    {
        return match (strtolower(trim($type))) {
            'textarea' => 'TEXT_AREA',
            'multi_select', 'multiselect' => 'MULTI_SELECT',
            default => strtoupper(trim($type)),
        };
    }
}
