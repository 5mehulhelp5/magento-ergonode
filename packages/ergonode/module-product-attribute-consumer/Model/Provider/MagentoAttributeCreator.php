<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Model\Provider;

use Ergonode\Attribute\Api\MagentoAttributeTypeResolverInterface;
use Ergonode\ProductAttributeConsumer\Model\Mapping\MagentoAttributeTypeRecommender;
use PackHauer\UnitAttribute\Api\UnitAttributeMetadataInterface;
use Throwable;

use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Backend\Price;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean;
use Magento\Eav\Model\Entity\Attribute\Source\Table;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class MagentoAttributeCreator
{
    private const array UNSUPPORTED_ERGONODE_TYPES = ['gallery', 'relation'];

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory,
        private readonly EavConfig $eavConfig,
        private readonly ProductAttributeRepositoryInterface $attributeRepository,
        private readonly UnitAttributeMetadataInterface $unitMetadata,
        private readonly MagentoAttributeTypeResolverInterface $typeResolver,
        private readonly MagentoAttributeTypeRecommender $attributeTypeRecommender
    ) {
    }

    /**
     * @param array{
     *     label: string,
     *     code: string,
     *     scope: string,
     *     type: string,
     *     parameters?: array<string, bool|string>,
     *     active?: bool
     * } $ergonodeAttribute
     * @return array{
     *     label: string,
     *     code: string,
     *     scope: string,
     *     type: string,
     *     active: bool,
     *     source: string,
     *     created: bool
     * }
     * @throws LocalizedException
     */
    public function previewFromErgonodeAttribute(array $ergonodeAttribute): array
    {
        $definition = $this->normalizeErgonodeDefinition($ergonodeAttribute);

        try {
            return $this->formatExistingAttribute($definition['code']);
        } catch (NoSuchEntityException) {
            // Attribute does not exist yet; return the data that would be created.
        }

        return $this->formatCreatedAttribute(
            $definition,
            $this->buildAttributeData($definition)
        );
    }

    /**
     * @param array{
     *     label: string,
     *     code: string,
     *     scope: string,
     *     type: string,
     *     parameters?: array<string, bool|string>,
     *     active?: bool
     * } $ergonodeAttribute
     * @return array{
     *     label: string,
     *     code: string,
     *     scope: string,
     *     type: string,
     *     active: bool,
     *     source: string,
     *     created: bool
     * }
     * @throws LocalizedException
     */
    public function createFromErgonodeAttribute(array $ergonodeAttribute): array
    {
        $definition = $this->normalizeErgonodeDefinition($ergonodeAttribute);

        try {
            return $this->formatExistingAttribute($definition['code']);
        } catch (NoSuchEntityException) {
            // Attribute does not exist yet; create it below.
        }

        $data = $this->buildAttributeData($definition);
        $connection = $this->moduleDataSetup->getConnection();
        $connection->startSetup();

        try {
            $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
            $eavSetup->addAttribute(Product::ENTITY, $definition['code'], $data);
        } catch (Throwable $exception) {
            throw new LocalizedException(
                __('Unable to create Magento attribute "%1": %2', $definition['code'], $exception->getMessage())
            );
        } finally {
            $connection->endSetup();
        }

        $this->eavConfig->clear();

        return $this->formatCreatedAttribute($definition, $data);
    }

    /**
     * @param array<string, mixed> $ergonodeAttribute
     * @return array{code: string, label: string, type: string, scope: string, parameters: array<string, bool|string>}
     * @throws LocalizedException
     */
    private function normalizeErgonodeDefinition(array $ergonodeAttribute): array
    {
        $code = $this->normalizeCode((string)($ergonodeAttribute['code'] ?? ''));
        $label = trim((string)($ergonodeAttribute['label'] ?? $code));
        $type = strtolower(trim((string)($ergonodeAttribute['type'] ?? '')));
        $scope = strtolower(trim((string)($ergonodeAttribute['scope'] ?? 'global')));
        $parameters = isset($ergonodeAttribute['parameters']) && is_array($ergonodeAttribute['parameters'])
            ? $ergonodeAttribute['parameters']
            : [];

        if ($code === '') {
            throw new LocalizedException(__('Ergonode attribute code is required.'));
        }
        if ($label === '') {
            throw new LocalizedException(__('Ergonode attribute label is required.'));
        }
        if (in_array($type, self::UNSUPPORTED_ERGONODE_TYPES, true)) {
            throw new LocalizedException(
                __('Ergonode attribute type "%1" cannot be created as a Magento product attribute.', $type)
            );
        }

        return [
            'code' => $code,
            'label' => $label,
            'type' => $type,
            'scope' => $scope,
            'parameters' => $parameters,
        ];
    }

    /**
     * @param array{
     *     code: string,
     *     label: string,
     *     type: string,
     *     scope: string,
     *     parameters: array<string, bool|string>
     * } $definition
     * @param array<string, mixed> $data
     * @return array{
     *     label: string,
     *     code: string,
     *     scope: string,
     *     type: string,
     *     active: bool,
     *     source: string,
     *     created: bool
     * }
     */
    private function formatCreatedAttribute(array $definition, array $data): array
    {
        return [
            'label' => $definition['label'],
            'code' => $definition['code'],
            'scope' => $this->formatScope((int)$data['global']),
            'type' => $this->typeResolver->fromStorage(
                (string)$data['input'],
                (string)$data['type'],
                (string)($data['source'] ?? '')
            ),
            'active' => true,
            'source' => 'magento',
            'created' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    private function buildAttributeData(array $definition): array
    {
        $label = $definition['label'];
        $type = $this->attributeTypeRecommender->recommend($definition['code'], $definition['type']);
        $scope = $definition['scope'];
        $data = [
            'backend' => '',
            'frontend' => '',
            'label' => $label,
            'frontend_class' => '',
            'source' => '',
            'global' => $this->resolveScope($scope),
            'visible' => true,
            'required' => false,
            'user_defined' => true,
            'default' => '',
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'visible_on_front' => false,
            'used_in_product_listing' => false,
            'group' => 'General',
        ];

        $typeData = match ($type) {
            'text' => [
                'type' => 'varchar',
                'input' => 'text',
            ],
            'textarea' => [
                'type' => 'text',
                'input' => 'textarea',
            ],
            'decimal', 'numeric' => [
                'type' => 'decimal',
                'input' => 'text',
                'frontend_class' => 'validate-number',
            ],
            'price' => [
                'type' => 'decimal',
                'input' => 'price',
                'backend' => Price::class,
                'frontend_class' => 'validate-number',
            ],
            'unit' => [
                'type' => 'decimal',
                'input' => 'unit',
                'frontend_class' => 'validate-number',
                'additional_data' => $this->unitAdditionalData($definition),
            ],
            'date' => [
                'type' => 'datetime',
                'input' => 'date',
            ],
            'boolean' => [
                'type' => 'int',
                'input' => 'boolean',
                'source' => Boolean::class,
                'default' => 0,
            ],
            'select' => [
                'type' => 'int',
                'input' => 'select',
                'source' => Table::class,
            ],
            'multiselect' => [
                'type' => 'varchar',
                'input' => 'multiselect',
                'backend' => ArrayBackend::class,
                'source' => Table::class,
            ],
            'file' => [
                'type' => 'varchar',
                'input' => 'file',
            ],
            'image' => [
                'type' => 'varchar',
                'input' => 'media_image',
            ],
            default => throw new LocalizedException(__('Unsupported Ergonode attribute type "%1".', $type)),
        };

        return array_replace($data, $typeData);
    }

    /**
     * @param array{code: string, parameters: array<string, bool|string>} $definition
     * @throws LocalizedException
     */
    private function unitAdditionalData(array $definition): string
    {
        $name = trim((string)($definition['parameters']['unitName'] ?? ''));
        $symbol = trim((string)($definition['parameters']['unitSymbol'] ?? ''));
        if ($name === '' || $symbol === '') {
            throw new LocalizedException(__(
                'Ergonode unit attribute "%1" requires unitName and unitSymbol parameters.',
                $definition['code']
            ));
        }

        return $this->unitMetadata->withUnit(null, $name, $symbol);
    }

    private function normalizeCode(string $code): string
    {
        $code = strtolower(trim($code));

        if ($code === '' || !preg_match('/^[a-z][a-z0-9_]{0,254}$/', $code)) {
            throw new LocalizedException(__('Magento attribute code "%1" is invalid.', $code));
        }

        return $code;
    }

    private function resolveScope(string $scope): int
    {
        return match ($scope) {
            'website' => ScopedAttributeInterface::SCOPE_WEBSITE,
            'local', 'store', 'store_view', 'store view' => ScopedAttributeInterface::SCOPE_STORE,
            default => ScopedAttributeInterface::SCOPE_GLOBAL,
        };
    }

    private function formatScope(int $scope): string
    {
        return match ($scope) {
            ScopedAttributeInterface::SCOPE_WEBSITE => 'website',
            ScopedAttributeInterface::SCOPE_STORE => 'store view',
            default => 'global',
        };
    }

    /**
     * @return array{
     *     label: string,
     *     code: string,
     *     scope: string,
     *     type: string,
     *     active: bool,
     *     source: string,
     *     created: bool
     * }
     * @throws NoSuchEntityException
     */
    private function formatExistingAttribute(string $code): array
    {
        $attribute = $this->attributeRepository->get($code);
        $frontendInput = (string)$attribute->getFrontendInput();
        $backendType = (string)$attribute->getBackendType();
        $scope = method_exists($attribute, 'getIsGlobal')
            ? (int)$attribute->getIsGlobal()
            : ScopedAttributeInterface::SCOPE_GLOBAL;

        return [
            'label' => (string)($attribute->getDefaultFrontendLabel() ?: $code),
            'code' => (string)$attribute->getAttributeCode(),
            'scope' => $this->formatScope($scope),
            'type' => $this->typeResolver->fromStorage(
                $frontendInput,
                $backendType,
                (string)$attribute->getSourceModel()
            ),
            'active' => true,
            'source' => 'magento',
            'created' => false,
        ];
    }
}
