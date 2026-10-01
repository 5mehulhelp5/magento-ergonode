<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\E2e\Support;

use Ergonode\Attribute\Api\MagentoAttributeTypeResolverInterface;
use Ergonode\ProductAttributeConsumer\Api\ProductAttributeMappingProviderInterface;
use Ergonode\ProductAttribute\Model\Provider\MagentoAttributeProvider;
use Ergonode\Template\Model\TemplateCodeNormalizer;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use RuntimeException;

class SampleDataManifestFactory
{
    private const array SUPPORTED_PRODUCT_TYPES = ['simple', 'configurable', 'grouped'];

    public function __construct(
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly ResourceConnection $resourceConnection,
        private readonly MagentoAttributeProvider $attributeProvider,
        private readonly ProductAttributeMappingProviderInterface $mappingProvider,
        private readonly MagentoAttributeTypeResolverInterface $typeResolver,
        private readonly TemplateCodeNormalizer $templateCodeNormalizer,
        private readonly CohortPlanner $cohortPlanner
    ) {
    }

    /** @return array<string, mixed> */
    public function create(string $seed, int $productLimit): array
    {
        $products = $this->products($seed, $productLimit);
        $selectedProducts = array_filter($products, static fn (array $product): bool => $product['selected']);
        if ($selectedProducts === []) {
            throw new RuntimeException('Magento Sample Data did not provide a product cohort.');
        }
        $attributeSetIds = array_values(array_unique(array_column($selectedProducts, 'attribute_set_id')));
        $attributes = $this->attributes($attributeSetIds, $seed);
        $roots = $this->roots($seed);
        $categories = $this->categories(array_map('strval', array_keys($roots)), $seed);
        $attributeSets = $this->attributeSets($attributeSetIds, $seed);

        return [
            'schema_version' => 1,
            'seed' => $seed,
            'ratios' => ['published' => 70, 'mapped' => 70, 'products' => 70],
            'products' => $products,
            'attributes' => $attributes,
            'categories' => $categories,
            'roots' => $roots,
            'templates' => $attributeSets,
        ];
    }

    /** @return array<string, array{sku: string, type: string, attribute_set_id: int, selected: bool}> */
    private function products(string $seed, int $limit): array
    {
        $mappedAttributeSetIds = array_fill_keys(array_map(
            'intval',
            $this->resourceConnection->getConnection()->fetchCol(
                $this->resourceConnection->getConnection()
                    ->select()
                    ->from(
                        $this->resourceConnection->getTableName('ergonode_template'),
                        ['attribute_set_id']
                    )
                    ->where('attribute_set_id IS NOT NULL')
            )
        ), true);
        $collection = $this->productCollectionFactory->create();
        $collection->addFieldToFilter('type_id', ['in' => self::SUPPORTED_PRODUCT_TYPES]);
        $byType = [];
        foreach ($collection as $product) {
            if (!$product instanceof Product || trim((string)$product->getSku()) === '') {
                continue;
            }
            if (isset($mappedAttributeSetIds[(int)$product->getAttributeSetId()])) {
                continue;
            }
            $byType[(string)$product->getTypeId()][] = [
                'sku' => trim((string)$product->getSku()),
                'type' => (string)$product->getTypeId(),
                'attribute_set_id' => (int)$product->getAttributeSetId(),
            ];
        }
        foreach ($byType as $type => $items) {
            usort($items, static fn (array $left, array $right): int => strcmp(
                hash('sha256', $seed . '|candidate|' . $left['sku']),
                hash('sha256', $seed . '|candidate|' . $right['sku'])
            ));
            $byType[$type] = $items;
        }

        $grouped = array_slice($byType['grouped'] ?? [], 0, min(5, $limit));
        $remaining = max(0, $limit - count($grouped));
        $configurableLimit = min(count($byType['configurable'] ?? []), max(1, (int)round($remaining * 0.35)));
        $configurable = array_slice($byType['configurable'] ?? [], 0, $configurableLimit);
        $remaining -= count($configurable);
        $simple = array_slice($byType['simple'] ?? [], 0, $remaining);
        $candidates = [...$simple, ...$configurable, ...$grouped];
        $candidateSkus = [];
        foreach ($candidates as $candidate) {
            $candidateSkus[$candidate['type']][] = $candidate['sku'];
        }
        $selection = $this->cohortPlanner->selectByGroup($candidateSkus, $seed . '|products');
        $result = [];
        foreach ($candidates as $candidate) {
            $candidate['selected'] = $selection[$candidate['sku']] ?? false;
            $result[$candidate['sku']] = $candidate;
        }
        ksort($result);

        return $result;
    }

    /** @param int[] $attributeSetIds @return array<string, array<string, mixed>> */
    private function attributes(array $attributeSetIds, string $seed): array
    {
        $connection = $this->resourceConnection->getConnection();
        $codes = $connection->fetchCol(
            $connection->select()
                ->from(
                    ['assignment' => $this->resourceConnection->getTableName('eav_entity_attribute')],
                    []
                )
                ->join(
                    ['attribute' => $this->resourceConnection->getTableName('eav_attribute')],
                    'attribute.attribute_id = assignment.attribute_id',
                    ['attribute_code']
                )
                ->where('assignment.attribute_set_id IN (?)', $attributeSetIds)
                ->distinct()
        );
        $available = $this->attributeProvider->getAttributeMap();
        $mappedMagentoCodes = array_fill_keys(array_column(
            $this->mappingProvider->getMappings(),
            'magento_attribute_code'
        ), true);
        $selected = [];
        foreach ($codes as $code) {
            $attribute = $available[(string)$code] ?? null;
            if (!is_array($attribute)
                || !empty($attribute['has_custom_source'])
                || isset($mappedMagentoCodes[(string)$code])
            ) {
                continue;
            }
            $ergonodeType = $this->typeResolver->toErgonodeType((string)$attribute['type']);
            if ($ergonodeType === null || in_array($ergonodeType, ['image', 'file', 'gallery'], true)) {
                continue;
            }
            $selected[(string)$code] = $attribute + ['ergonode_type' => $ergonodeType];
        }
        $cohorts = $this->cohortPlanner->plan(array_keys($selected), $seed . '|attributes');
        foreach ($selected as $code => &$attribute) {
            $attribute['remote_code'] = $code;
            $attribute['cohort'] = $cohorts[$code];
        }
        unset($attribute);
        ksort($selected);

        if (count($selected) < 10) {
            throw new RuntimeException('The E2E scenario requires at least ten mappable Sample Data attributes.');
        }

        return $selected;
    }

    /** @return array<string, array<string, mixed>> */
    private function roots(string $seed): array
    {
        $configuredRootIds = array_fill_keys(array_map(
            'intval',
            $this->resourceConnection->getConnection()->fetchCol(
                $this->resourceConnection->getConnection()
                    ->select()
                    ->from(
                        $this->resourceConnection->getTableName('ergonode_category_tree'),
                        ['root_category_id']
                    )
            )
        ), true);
        $roots = [];
        foreach ($this->storeManager->getGroups() as $group) {
            $rootId = (int)$group->getRootCategoryId();
            if ($rootId <= 0 || isset($configuredRootIds[$rootId])) {
                continue;
            }
            $roots[(string)$rootId] = [
                'root_category_id' => $rootId,
                'name' => (string)$group->getName(),
                'tree_code' => $this->remoteCode($seed, 'root_' . $rootId),
            ];
        }
        $cohorts = $this->cohortPlanner->planRoots(array_keys($roots), $seed . '|roots');
        $result = [];
        foreach ($cohorts as $rootId => $cohort) {
            $result[$rootId] = $roots[$rootId] + ['cohort' => $cohort];
        }
        ksort($result);

        return $result;
    }

    /** @param string[] $rootIds @return array<string, array<string, mixed>> */
    private function categories(array $rootIds, string $seed): array
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect('name');
        $categories = [];
        foreach ($collection as $category) {
            if (!$category instanceof Category || in_array((string)$category->getId(), $rootIds, true)) {
                continue;
            }
            $rootId = $this->categoryRootId((string)$category->getPath(), $rootIds);
            if ($rootId === null) {
                continue;
            }
            $id = (int)$category->getId();
            $categories[(string)$id] = [
                'magento_category_id' => $id,
                'root_category_id' => (int)$rootId,
                'parent_id' => (int)$category->getParentId(),
                'name' => (string)$category->getName(),
                'remote_code' => $this->remoteCode($seed, 'category_' . $id),
            ];
        }
        $cohorts = $this->cohortPlanner->plan(array_keys($categories), $seed . '|categories');
        foreach ($categories as $id => &$category) {
            $category['cohort'] = $cohorts[$id];
        }
        unset($category);
        ksort($categories);

        if (count($categories) < 10) {
            throw new RuntimeException('The E2E scenario requires at least ten Sample Data categories across roots.');
        }

        return $categories;
    }

    /** @param int[] $attributeSetIds @return array<string, array<string, mixed>> */
    private function attributeSets(array $attributeSetIds, string $seed): array
    {
        if ($attributeSetIds === []) {
            return [];
        }
        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(
                    $this->resourceConnection->getTableName('eav_attribute_set'),
                    ['attribute_set_id', 'attribute_set_name']
                )
                ->where('attribute_set_id IN (?)', $attributeSetIds)
        );
        $result = [];
        foreach ($rows as $row) {
            $id = (int)$row['attribute_set_id'];
            $name = (string)$row['attribute_set_name'];
            $result[(string)$id] = [
                'attribute_set_id' => $id,
                'name' => $name,
                'template_code' => $this->templateCodeNormalizer->normalize($name),
            ];
        }
        $cohorts = $this->cohortPlanner->plan(array_keys($result), $seed . '|templates');
        foreach ($result as $id => &$attributeSet) {
            $attributeSet['cohort'] = $cohorts[$id];
        }
        unset($attributeSet);
        ksort($result);

        return $result;
    }

    /** @param string[] $rootIds */
    private function categoryRootId(string $path, array $rootIds): ?string
    {
        $pathIds = explode('/', $path);
        foreach ($rootIds as $rootId) {
            if (in_array($rootId, $pathIds, true)) {
                return $rootId;
            }
        }

        return null;
    }

    private function remoteCode(string $seed, string $code): string
    {
        $normalized = preg_replace('/[^a-z0-9_]+/', '_', strtolower($code)) ?: 'entity';

        return mb_substr('e2e_' . substr(hash('sha256', $seed), 0, 8) . '_' . trim($normalized, '_'), 0, 128);
    }
}
