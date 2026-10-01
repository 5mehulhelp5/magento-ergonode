<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Sync;

use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filter\TranslitUrl;
use Magento\Store\Model\Store;

class CategoryCreator
{
    public function __construct(
        private readonly CategoryFactory $categoryFactory,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly TranslitUrl $translitUrl,
        private readonly State $state,
        private readonly LanguageStoreMappingProviderInterface $languageMappingProvider
    ) {
    }

    /**
     * @return array{id: int, parent_id: int, label: string, path: string, level: int, position: int, url_key: string}
     * @throws LocalizedException
     */
    public function create(string $label, int $parentId, array $creationValues): array
    {
        $this->languageMappingProvider->requireAdminLanguageCode();
        foreach (['is_active', 'include_in_menu'] as $attributeCode) {
            if (!array_key_exists($attributeCode, $creationValues)) {
                throw new LocalizedException(__('Category creation value "%1" is not configured.', $attributeCode));
            }
        }

        return $this->state->emulateAreaCode(
            Area::AREA_ADMINHTML,
            function () use ($label, $parentId, $creationValues): array {
                $urlKey = $this->buildUrlKey($label);
                $category = $this->categoryFactory->create();
                $category->setStoreId(Store::DEFAULT_STORE_ID)
                    ->setName($label)
                    ->setData('url_key', $urlKey)
                    ->setIsActive((int)(bool)$creationValues['is_active'])
                    ->setIncludeInMenu((int)(bool)$creationValues['include_in_menu'])
                    ->setParentId($parentId);
                $category->isObjectNew(true);

                // Magento derives path/level; an input path becomes a stale EAV custom attribute during save.
                $category = $this->categoryRepository->save($category);

                return [
                    'id' => (int)$category->getId(),
                    'parent_id' => (int)$category->getParentId(),
                    'label' => (string)$category->getName(),
                    'path' => (string)$category->getPath(),
                    'level' => (int)$category->getLevel(),
                    'position' => (int)$category->getPosition(),
                    'url_key' => (string)($category->getCustomAttribute('url_key')?->getValue() ?? ''),
                ];
            }
        );
    }

    private function buildUrlKey(string $label): string
    {
        $urlKey = trim((string)$this->translitUrl->filter($label), '-');
        if ($urlKey === '') {
            throw new LocalizedException(__('Category name cannot be converted to a URL key.'));
        }

        return $urlKey;
    }
}
