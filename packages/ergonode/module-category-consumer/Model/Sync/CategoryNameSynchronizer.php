<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Sync;

use Ergonode\CategoryConsumer\Api\CategoryNameSynchronizerInterface;
use Ergonode\CategoryConsumer\Api\CategoryNameTargetProviderInterface;
use Ergonode\CategoryConsumer\Api\CategoryNameWriterInterface;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;

class CategoryNameSynchronizer implements CategoryNameSynchronizerInterface
{
    public function __construct(
        private readonly CategoryNameTargetProviderInterface $targetProvider,
        private readonly LanguageStoreMappingProviderInterface $languageMappingProvider,
        private readonly CategoryNameWriterInterface $writer
    ) {
    }

    public function synchronize(int $categoryId, array $labels): int
    {
        $target = $this->targetProvider->getAttributeCode();
        if ($target === null) {
            return 0;
        }
        $values = [];
        foreach ($this->languageMappingProvider->getLanguageStoreMap() as $storeId => $language) {
            $value = trim((string)($labels[$language] ?? ''));
            // Never remove the required default name. Missing store translations inherit it.
            if ($storeId === 0 && $target === 'name' && $value === '') {
                continue;
            }
            $values[$storeId] = $value !== '' ? $value : null;
        }

        return $this->writer->write($categoryId, $target, $values);
    }
}
