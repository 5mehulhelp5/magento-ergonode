<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisherAdminUi\Model\Mapping;

use Ergonode\CategoryAttributeAdminUi\Api\SourceMetadataProviderInterface;
use Ergonode\AttributePublisher\Api\AttributeBatchStateLoaderInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeStateInterface;
use Ergonode\CategoryAttributePublisher\Api\WriteScopeCategoryAttributeCodeLoaderInterface;
use Ergonode\Core\Model\Config\ConfigProvider;

class SourceMetadata implements SourceMetadataProviderInterface
{
    /** @var array<string, AttributeStateInterface>|null */
    private ?array $states = null;

    public function __construct(
        private readonly WriteScopeCategoryAttributeCodeLoaderInterface $registry,
        private readonly AttributeBatchStateLoaderInterface $loader,
        private readonly ConfigProvider $config
    ) {
    }

    public function reset(): void
    {
        $this->states = null;
    }

    public function getAttributes(): array
    {
        $attributes = [];
        foreach ($this->getStates() as $state) {
            $names = $state->getNames();
            $attributes[] = [
                'code' => $state->getCode(),
                'label' => (string)(reset($names) ?: $state->getCode()),
                'type' => $this->normalizeType($state->getType()),
                'scope' => strtolower($state->getScope()),
                'parameters' => $state->getParameters(),
                'active' => true,
            ];
        }
        return $attributes;
    }

    public function getOptions(string $attributeCode): array
    {
        $state = $this->getStates()[$attributeCode] ?? null;
        $options = [];
        foreach ($state?->getOptions() ?? [] as $option) {
            $names = $option->getNames();
            $options[] = [
                'code' => $option->getCode(),
                'label' => (string)(reset($names) ?: $option->getCode()),
                'type' => 'option', 'active' => true,
            ];
        }
        return $options;
    }

    /** @return array<string, AttributeStateInterface> */
    private function getStates(): array
    {
        if ($this->states !== null) {
            return $this->states;
        }
        $states = [];
        if ($this->config->allowsWrites()) {
            foreach ($this->loader->loadBatch($this->registry->loadWriteScope()) as $code => $state) {
                if ($state !== null) {
                    $states[$code] = $state;
                }
            }
        }
        return $this->states = $states;
    }

    private function normalizeType(string $type): string
    {
        return str_replace('multi_select', 'multiselect', strtolower($type));
    }
}
