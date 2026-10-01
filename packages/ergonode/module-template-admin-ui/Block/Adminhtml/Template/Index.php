<?php

declare(strict_types=1);

namespace Ergonode\TemplateAdminUi\Block\Adminhtml\Template;

use Ergonode\Core\Api\MappingVisibilityProviderInterface;
use Ergonode\TemplateAdminUi\Api\WorkspaceConfigProviderInterface;
use Ergonode\TemplateAdminUi\Model\TemplateUiProvider;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Serialize\Serializer\Json;

class Index extends Template
{
    public const string NAVIGATION_SECTION = 'templates';

    /** @var array<int, array<string, mixed>>|null */
    private ?array $templates = null;

    /** @var array<int, array{id: int, name: string, active: bool}>|null */
    private ?array $attributeSets = null;

    /** @param WorkspaceConfigProviderInterface[] $configProviders */
    public function __construct(
        Context $context,
        private readonly TemplateUiProvider $templateUiProvider,
        private readonly MappingVisibilityProviderInterface $visibilityProvider,
        private readonly Json $json,
        private readonly FormKey $formKeyProvider,
        private readonly array $configProviders = [],
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getTemplateConfigJson(): string
    {
        return $this->json->serialize(array_replace_recursive([
            'templates' => $this->getTemplates(),
            'attribute_sets' => $this->getAttributeSets(),
            'can_create_attribute_sets' => false,
            'urls' => [
                'save_mapping' => $this->getUrl('ergonode/template/saveMapping'),
                'language_mapping' => $this->getUrl('ergonode/language/index'),
            ],
            'form_key' => $this->formKeyProvider->getFormKey(),
        ], ...array_map(
            static fn (WorkspaceConfigProviderInterface $provider): array => $provider->getConfig(),
            array_values($this->configProviders)
        )));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTemplates(): array
    {
        if ($this->templates !== null) {
            return $this->templates;
        }

        $templates = $this->templateUiProvider->getTemplates();
        $identifiers = array_map(
            static fn (array $template): string => (string)$template['code'],
            $templates
        );
        $activeMap = $this->visibilityProvider->getActiveMap('template', 'ergo', $identifiers);

        return $this->templates = array_map(
            static function (array $template) use ($activeMap): array {
                $identifier = (string)$template['code'];
                $template['active'] = empty($template['is_deleted']) && ($activeMap[$identifier] ?? true);

                return $template;
            },
            $templates
        );
    }

    /**
     * @return array<int, array{id: int, name: string, active: bool}>
     */
    public function getAttributeSets(): array
    {
        if ($this->attributeSets !== null) {
            return $this->attributeSets;
        }

        $attributeSets = $this->templateUiProvider->getAttributeSets();
        $identifiers = array_map(
            static fn (array $attributeSet): string => (string)$attributeSet['id'],
            $attributeSets
        );
        $activeMap = $this->visibilityProvider->getActiveMap('template', 'magento', $identifiers);

        return $this->attributeSets = array_map(
            static function (array $attributeSet) use ($activeMap): array {
                $identifier = (string)$attributeSet['id'];
                $attributeSet['active'] = $activeMap[$identifier] ?? true;

                return $attributeSet;
            },
            $attributeSets
        );
    }
}
