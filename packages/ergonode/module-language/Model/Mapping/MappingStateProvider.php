<?php

declare(strict_types=1);

namespace Ergonode\Language\Model\Mapping;

use Ergonode\Core\Api\MappingVisibilityProviderInterface;
use Ergonode\Language\Api\ErgonodeLanguageCodesProviderInterface;
use Ergonode\Language\Api\LanguageMappingStateProviderInterface;
use Ergonode\Language\Api\LanguageStoreMappingRowsProviderInterface;
use Ergonode\Language\Api\StoreViewProviderInterface;
use Ergonode\Language\Model\Data\MappingStateDto;
use Magento\Framework\Serialize\Serializer\Json;

class MappingStateProvider implements LanguageMappingStateProviderInterface
{
    public function __construct(
        private readonly LanguageStoreMappingRowsProviderInterface $rowsProvider,
        private readonly ErgonodeLanguageCodesProviderInterface $codesProvider,
        private readonly StoreViewProviderInterface $storeViewProvider,
        private readonly MappingVisibilityProviderInterface $visibilityProvider,
        private readonly MappingLock $lock,
        private readonly Json $json
    ) {
    }

    public function getState(): MappingStateDto
    {
        return $this->lock->run(function (): MappingStateDto {
            $codes = $this->codesProvider->getErgonodeLanguageCodes();
            $rows = $this->rowsProvider->getRows();
            $stores = $this->storeViewProvider->getStoreViewMap();
            $languageVisibility = $this->visibilityProvider->getActiveMap('language', 'ergo', $codes);
            $storeVisibility = $this->visibilityProvider->getActiveMap(
                'language',
                'magento',
                array_map('strval', array_keys($stores))
            );
            $storeVisibility['0'] = true;

            return new MappingStateDto(
                $codes,
                $rows,
                $stores,
                $languageVisibility,
                $storeVisibility,
                hash('sha256', $this->json->serialize([$codes, $rows, $stores, $languageVisibility, $storeVisibility]))
            );
        });
    }
}
