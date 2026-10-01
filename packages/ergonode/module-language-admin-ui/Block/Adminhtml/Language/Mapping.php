<?php

declare(strict_types=1);

namespace Ergonode\LanguageAdminUi\Block\Adminhtml\Language;

use Ergonode\Language\Api\LanguageMappingStateProviderInterface;
use Ergonode\Language\Model\Data\MappingStateDto;
use Ergonode\LanguageAdminUi\Model\LocaleLabelProvider;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;

class Mapping extends Template
{
    private const string ACL_REFRESH = 'Ergonode_Language::language_mapping_refresh';
    private const string ACL_SAVE = 'Ergonode_Language::language_mapping_save';

    private ?MappingStateDto $state = null;

    public function __construct(
        Context $context,
        private readonly LanguageMappingStateProviderInterface $stateProvider,
        private readonly LocaleLabelProvider $localeLabelProvider,
        private readonly Json $json,
        private readonly FormKey $mappingFormKey,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array<int, array{label: string, code: string, active: bool}>
     */
    public function getErgonodeLanguages(): array
    {
        $codes = $this->getState()->codes;
        $codes = array_values(array_unique(array_filter(array_map('trim', $codes))));
        natcasesort($codes);

        $codes = array_values($codes);
        $activeMap = $this->getState()->languageVisibility;

        return array_map(
            fn (string $code): array => [
                'label' => $this->localeLabelProvider->getLabel($code),
                'code' => $code,
                'active' => $activeMap[$code] ?? true,
            ],
            $codes
        );
    }

    public function getStoreViews(): array
    {
        $storeViews = array_values($this->getState()->stores);
        $activeMap = $this->getState()->storeVisibility;

        return array_map(
            static function (array $storeView) use ($activeMap): array {
                $storeView['active'] = $activeMap[(string)$storeView['id']] ?? true;

                return $storeView;
            },
            $storeViews
        );
    }

    /**
     * @return array<int, array{
     *     active: bool,
     *     left: array{label: string, code: string}|null,
     *     right: array{id: int, code: string, name: string, locale: string, website: string, group: string}|null
     * }>
     */
    public function getMappings(): array
    {
        $state = $this->getState();
        $storeViews = $state->stores;
        $languages = array_fill_keys($state->codes, true);
        $mappings = [];

        foreach ($this->getState()->rows as $row) {
            $storeId = $row['store_id'];
            $languageCode = $row['language_code'];
            if ($storeId !== null && !isset($storeViews[$storeId])) {
                continue;
            }

            $mappings[] = [
                'active' => $languageCode !== null && isset($languages[$languageCode]) && $storeId !== null
                    && ($state->languageVisibility[$languageCode] ?? true)
                    && ($state->storeVisibility[(string)$storeId] ?? true),
                'left' => $languageCode !== null ? [
                    'label' => $this->localeLabelProvider->getLabel($languageCode),
                    'code' => $languageCode,
                ] : null,
                'right' => $storeId !== null ? $storeViews[$storeId] : null,
            ];
        }

        return $mappings;
    }

    /**
     * @throws LocalizedException
     */
    public function getMappingConfigJson(): string
    {
        return $this->json->serialize([
            'urls' => [
                'refresh' => $this->getUrl('ergonode/language/refresh'),
                'save' => $this->getUrl('ergonode/language/save'),
                'delete_snapshot' => $this->getUrl('ergonode/language/deleteSnapshot'),
                'configuration' => $this->getUrl(
                    'adminhtml/system_config/edit',
                    ['section' => 'ergonode_connection']
                ),
            ],
            'revision' => $this->getState()->revision,
            'canSave' => $this->canSaveMappings(),
            'form_key' => $this->mappingFormKey->getFormKey(),
        ]);
    }

    public function canRefreshLanguages(): bool
    {
        return $this->getAuthorization()->isAllowed(self::ACL_REFRESH);
    }

    public function canSaveMappings(): bool
    {
        return $this->getAuthorization()->isAllowed(self::ACL_SAVE);
    }

    private function getState(): MappingStateDto
    {
        return $this->state ??= $this->stateProvider->getState();
    }
}
