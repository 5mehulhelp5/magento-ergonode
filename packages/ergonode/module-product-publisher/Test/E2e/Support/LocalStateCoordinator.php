<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\E2e\Support;

use Ergonode\ProductAttributeConsumer\Model\Import\AttributeImportProcess;
use Ergonode\ProductAttributeConsumer\Model\Mapping\AttributeMappingProvider;
use Ergonode\ProductAttributeConsumer\Model\Mapping\AttributeMappingSaver;
use Ergonode\ProductAttributeConsumer\Model\Mapping\OptionMappingSaver;
use Ergonode\AttributeConsumer\Model\Provider\ErgonodeAttributeProvider;
use Ergonode\AttributeConsumer\Model\Provider\ErgonodeOptionProvider;
use Ergonode\ProductAttribute\Model\Provider\MagentoOptionProvider;
use Ergonode\CategoryConsumer\Api\CategoryReconciliationServiceInterface;
use Ergonode\CategoryConsumer\Api\Data\CategoryReconciliationRequestInterface;
use Ergonode\Category\Model\CategoryTree\CategoryTreeRepository;
use Ergonode\CategoryConsumer\Model\Data\CategoryReconciliationRequest;
use Ergonode\TemplateConsumer\Api\TemplateSynchronizerInterface;
use Ergonode\Template\Model\Mapping\TemplateAttributeSetMappingSaver;
use Magento\Framework\App\ResourceConnection;
use RuntimeException;

class LocalStateCoordinator
{
    /** @var array<int, array<string, mixed>> */
    private array $originalAttributeMappings;

    /** @var array<string, int> */
    private array $originalTemplateMappings;

    /** @var array<int, int> */
    private array $createdCategoryTreeIds = [];

    public function __construct(
        private readonly AttributeMappingProvider $attributeMappingProvider,
        private readonly AttributeMappingSaver $attributeMappingSaver,
        private readonly ErgonodeAttributeProvider $ergonodeAttributeProvider,
        private readonly ErgonodeOptionProvider $ergonodeOptionProvider,
        private readonly MagentoOptionProvider $magentoOptionProvider,
        private readonly OptionMappingSaver $optionMappingSaver,
        private readonly AttributeImportProcess $attributeImportProcess,
        private readonly TemplateSynchronizerInterface $templateSynchronizer,
        private readonly TemplateAttributeSetMappingSaver $templateMappingSaver,
        private readonly CategoryTreeRepository $categoryTreeRepository,
        private readonly CategoryReconciliationServiceInterface $categoryReconciliationService,
        private readonly ResourceConnection $resourceConnection
    ) {
        $this->originalAttributeMappings = $this->attributeMappingProvider->getMappings();
        $this->originalTemplateMappings = $this->loadTemplateMappings();
    }

    /** @param array<string, mixed> $manifest @param string[] $cohorts @param string[] $forceCreateCodes */
    public function saveAttributeMappings(array $manifest, array $cohorts, array $forceCreateCodes = []): void
    {
        $mappings = $this->originalAttributeMappings;
        $forceCreate = array_fill_keys($forceCreateCodes, true);
        foreach ((array)$manifest['attributes'] as $attribute) {
            if (!in_array((string)$attribute['cohort'], $cohorts, true)) {
                continue;
            }
            $code = (string)$attribute['remote_code'];
            $remote = isset($forceCreate[$code]) ? null : $this->ergonodeAttributeProvider->getAttribute($code);
            $mappings[] = [
                'left' => $remote ?: [
                    'code' => $code,
                    'label' => (string)$attribute['label'],
                    'type' => (string)$attribute['ergonode_type'],
                    'scope' => 'local',
                    'pending_create' => true,
                ],
                'right' => $attribute,
            ];
        }
        $this->attributeMappingSaver->save($mappings, []);
    }

    public function importAttributes(bool $reset): array
    {
        if ($reset) {
            $this->attributeImportProcess->reset();
        }

        return $this->attributeImportProcess->executeUntilComplete(100, 200);
    }

    /** @param array<string, mixed> $manifest @param string[] $cohorts */
    public function saveOptionMappings(array $manifest, array $cohorts): void
    {
        foreach ((array)$manifest['attributes'] as $attribute) {
            if (!in_array((string)$attribute['cohort'], $cohorts, true)
                || !in_array((string)$attribute['ergonode_type'], ['select', 'multi_select'], true)
            ) {
                continue;
            }
            $remoteCode = (string)$attribute['remote_code'];
            $mappingId = $this->attributeMappingId($remoteCode, (string)$attribute['code']);
            if ($mappingId <= 0) {
                throw new RuntimeException(
                    'Missing saved attribute mapping for option synchronization: ' . $remoteCode
                );
            }
            $remoteOptions = [];
            foreach ($this->ergonodeOptionProvider->getOptions($remoteCode) as $option) {
                $remoteOptions[(string)$option['code']] = $option;
            }
            $mappings = [];
            foreach ($this->magentoOptionProvider->getOptions((string)$attribute['code']) as $option) {
                $code = (string)$option['code'];
                $mappings[] = [
                    'left' => $remoteOptions[$code] ?? [
                        'code' => $code,
                        'label' => (string)$option['label'],
                        'type' => 'option',
                        'scope' => 'local',
                        'pending_create' => true,
                    ],
                    'right' => $option,
                ];
            }
            $this->optionMappingSaver->save($mappingId, $mappings, []);
        }
    }

    public function importTemplates(bool $reset): array
    {
        return $this->templateSynchronizer->execute($reset);
    }

    /** @param array<string, mixed> $manifest @param string[] $cohorts */
    public function saveTemplateMappings(array $manifest, array $cohorts): void
    {
        $mappings = $this->originalTemplateMappings;
        foreach ((array)$manifest['templates'] as $template) {
            if (in_array((string)$template['cohort'], $cohorts, true)) {
                $mappings[(string)$template['template_code']] = (int)$template['attribute_set_id'];
            }
        }
        $this->templateMappingSaver->save($mappings);
    }

    /** @param array<string, mixed> $manifest @param string[] $cohorts */
    public function configureAndImportCategories(array $manifest, array $cohorts): void
    {
        foreach ((array)$manifest['roots'] as $root) {
            if (!in_array((string)$root['cohort'], $cohorts, true)) {
                continue;
            }
            $rootCategoryId = (int)$root['root_category_id'];
            $categoryTree = [
                'is_active' => 1,
                'tree_code' => (string)$root['tree_code'],
                'root_category_id' => $rootCategoryId,
                'remove_missing' => 0,
            ];
            if (isset($this->createdCategoryTreeIds[$rootCategoryId])) {
                $categoryTree['category_tree_id'] = $this->createdCategoryTreeIds[$rootCategoryId];
            }
            $categoryTreeId = $this->categoryTreeRepository->save($categoryTree);
            $this->createdCategoryTreeIds[$rootCategoryId] = $categoryTreeId;
            $draftMappings = [];
            $visibility = [];
            foreach ((array)$manifest['categories'] as $category) {
                if ((int)$category['root_category_id'] !== (int)$root['root_category_id']) {
                    continue;
                }
                $mapped = in_array((string)$category['cohort'], $cohorts, true);
                if ($mapped) {
                    $draftMappings[] = [
                        'ergonode_code' => (string)$category['remote_code'],
                        'magento_category_id' => (int)$category['magento_category_id'],
                    ];
                }
                $visibility[] = [
                    'source' => 'ergo',
                    'identifier' => (string)$category['remote_code'],
                    'active' => $mapped,
                ];
            }
            $result = $this->categoryReconciliationService->execute(
                (new CategoryReconciliationRequest())
                    ->setCategoryTreeId($categoryTreeId)
                    ->setMode(CategoryReconciliationRequestInterface::MODE_APPLY)
                    ->setDraftMappings($draftMappings)
                    ->setDraftVisibility($visibility)
            );
            if ($result->getConflicts() !== []) {
                throw new RuntimeException(
                    'Category reconciliation conflicts: ' . implode('; ', $result->getConflicts())
                );
            }
        }
    }

    public function cleanup(): void
    {
        try {
            foreach (array_reverse(array_values(array_unique($this->createdCategoryTreeIds))) as $categoryTreeId) {
                $this->categoryTreeRepository->deleteById($categoryTreeId);
            }
        } finally {
            try {
                $this->templateMappingSaver->save($this->originalTemplateMappings);
            } finally {
                $this->attributeMappingSaver->save($this->originalAttributeMappings, []);
            }
        }
    }

    /** @return array<string, int> */
    private function loadTemplateMappings(): array
    {
        $rows = $this->resourceConnection->getConnection()->fetchPairs(
            $this->resourceConnection->getConnection()
                ->select()
                ->from(
                    $this->resourceConnection->getTableName('ergonode_template'),
                    ['code', 'attribute_set_id']
                )
                ->where('attribute_set_id IS NOT NULL')
        );

        return array_map('intval', $rows);
    }

    private function attributeMappingId(string $ergonodeCode, string $magentoCode): int
    {
        return (int)$this->resourceConnection->getConnection()->fetchOne(
            $this->resourceConnection->getConnection()
                ->select()
                ->from($this->resourceConnection->getTableName('ergonode_product_attribute_mapping'), ['mapping_id'])
                ->where('ergonode_attribute_code = ?', $ergonodeCode)
                ->where('magento_attribute_code = ?', $magentoCode)
                ->limit(1)
        );
    }
}
