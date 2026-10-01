<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\Provider;

use Ergonode\Attribute\Api\MagentoAttributeTypeResolverInterface;
use Magento\Catalog\Model\Category;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean;
use Magento\Eav\Model\Entity\Attribute\Source\Table;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Throwable;

class MagentoCategoryAttributeCreator
{
    private const array UNSUPPORTED_TYPES = ['gallery', 'relation'];

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory,
        private readonly EavConfig $eavConfig,
        private readonly MagentoAttributeTypeResolverInterface $typeResolver
    ) {
    }

    /**
     * @param array<string, mixed> $ergonodeAttribute
     * @return array<string, mixed>
     */
    public function previewFromErgonodeAttribute(array $ergonodeAttribute): array
    {
        $definition = $this->normalizeDefinition($ergonodeAttribute);
        $existing = $this->formatExisting($definition['code']);

        return $existing ?? $this->formatCreated($definition, $this->buildAttributeData($definition), true);
    }

    /** @param array<string, mixed> $ergonodeAttribute */
    public function createFromErgonodeAttribute(array $ergonodeAttribute): array
    {
        $definition = $this->normalizeDefinition($ergonodeAttribute);
        $existing = $this->formatExisting($definition['code']);
        if ($existing !== null) {
            return $existing;
        }

        $data = $this->buildAttributeData($definition);
        $connection = $this->moduleDataSetup->getConnection();
        $connection->startSetup();
        try {
            $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup])
                ->addAttribute(Category::ENTITY, $definition['code'], $data);
        } catch (Throwable $exception) {
            throw new LocalizedException(__(
                'Unable to create Magento category attribute "%1": %2',
                $definition['code'],
                $exception->getMessage()
            ));
        } finally {
            $connection->endSetup();
        }

        $this->eavConfig->clear();

        return $this->formatCreated($definition, $data, true);
    }

    /**
     * @param array<string, mixed> $attribute
     * @return array{code: string, label: string, type: string, scope: string}
     */
    private function normalizeDefinition(array $attribute): array
    {
        $code = strtolower(trim((string)($attribute['code'] ?? '')));
        $label = trim((string)($attribute['label'] ?? $code));
        $type = strtolower(trim((string)($attribute['type'] ?? '')));
        $scope = strtolower(trim((string)($attribute['scope'] ?? 'global')));

        if ($code === '' || !preg_match('/^[a-z][a-z0-9_]{0,254}$/', $code)) {
            throw new LocalizedException(__('Magento category attribute code "%1" is invalid.', $code));
        }
        if ($label === '') {
            throw new LocalizedException(__('Ergonode category attribute label is required.'));
        }
        if (in_array($type, self::UNSUPPORTED_TYPES, true)) {
            throw new LocalizedException(__(
                'Ergonode attribute type "%1" cannot be created as a Magento category attribute.',
                $type
            ));
        }

        return ['code' => $code, 'label' => $label, 'type' => $type, 'scope' => $scope];
    }

    /**
     * @param array{code: string, label: string, type: string, scope: string} $definition
     * @return array<string, mixed>
     */
    private function buildAttributeData(array $definition): array
    {
        $data = [
            'label' => $definition['label'],
            'global' => in_array($definition['scope'], ['local', 'store', 'store view', 'store_view'], true) ? 0 : 1,
            'visible' => true,
            'required' => false,
            'user_defined' => true,
            'group' => 'General Information',
            'sort_order' => 200,
        ];

        $typeData = match ($definition['type']) {
            'text' => ['type' => 'varchar', 'input' => 'text'],
            'textarea' => ['type' => 'text', 'input' => 'textarea'],
            'numeric', 'price', 'unit' => [
                'type' => 'decimal',
                'input' => 'text',
                'frontend_class' => 'validate-number',
            ],
            'date' => ['type' => 'datetime', 'input' => 'date'],
            'boolean' => ['type' => 'int', 'input' => 'boolean', 'source' => Boolean::class],
            'select' => ['type' => 'int', 'input' => 'select', 'source' => Table::class],
            'multiselect' => [
                'type' => 'varchar',
                'input' => 'multiselect',
                'backend' => ArrayBackend::class,
                'source' => Table::class,
            ],
            'file' => ['type' => 'varchar', 'input' => 'file'],
            'image' => ['type' => 'varchar', 'input' => 'image'],
            default => throw new LocalizedException(__(
                'Unsupported Ergonode category attribute type "%1".',
                $definition['type']
            )),
        };

        return array_replace($data, $typeData);
    }

    /** @return array<string, mixed>|null */
    private function formatExisting(string $code): ?array
    {
        $attribute = $this->eavConfig->getAttribute(Category::ENTITY, $code);
        if (!(int)$attribute->getAttributeId()) {
            return null;
        }

        return [
            'label' => (string)($attribute->getDefaultFrontendLabel() ?: $code),
            'code' => $code,
            'scope' => (int)$attribute->getIsGlobal() === 0 ? 'store view' : 'global',
            'type' => $this->resolveType(
                (string)$attribute->getFrontendInput(),
                (string)$attribute->getBackendType(),
                (string)$attribute->getSourceModel()
            ),
            'active' => true,
            'source' => 'magento',
            'created' => false,
        ];
    }

    /**
     * @param array{code: string, label: string, type: string, scope: string} $definition
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function formatCreated(array $definition, array $data, bool $created): array
    {
        return [
            'label' => $definition['label'],
            'code' => $definition['code'],
            'scope' => (int)$data['global'] === 0 ? 'store view' : 'global',
            'type' => $this->resolveType(
                (string)$data['input'],
                (string)$data['type'],
                (string)($data['source'] ?? '')
            ),
            'active' => true,
            'source' => 'magento',
            'created' => $created,
        ];
    }

    private function resolveType(string $frontendInput, string $backendType, string $sourceModel): string
    {
        return $frontendInput === 'image'
            ? 'image'
            : $this->typeResolver->fromStorage($frontendInput, $backendType, $sourceModel);
    }
}
