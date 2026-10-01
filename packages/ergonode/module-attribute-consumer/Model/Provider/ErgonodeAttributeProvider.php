<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Model\Provider;

use Ergonode\AttributeConsumer\Api\ErgonodeRelationAttributeProviderInterface;
use Ergonode\AttributeConsumer\Api\ErgonodeAttributeProviderInterface;
use InvalidArgumentException;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\Serializer\Json;
use Ergonode\Core\Api\MappingVisibilityProviderInterface;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;

class ErgonodeAttributeProvider implements
    ErgonodeAttributeProviderInterface,
    ErgonodeRelationAttributeProviderInterface
{
    private const string ATTRIBUTE_TABLE = 'ergonode_attribute';
    private const array HIDDEN_ATTRIBUTE_TYPES = ['gallery', 'relation'];

    /**
     * @var array<int, array{
     *     label: string,
     *     code: string,
     *     scope: string,
     *     type: string,
     *     parameters: array<string, bool|string>,
     *     active: bool
     * }>|null
     */
    private ?array $attributesCache = null;

    /**
     * @var array<string, array{
     *     label: string,
     *     code: string,
     *     scope: string,
     *     type: string,
     *     parameters: array<string, bool|string>,
     *     active: bool
     * }>|null
     */
    private ?array $attributeMapCache = null;

    /** @var list<array{label: string, code: string, scope: string}>|null */
    private ?array $relationAttributesCache = null;

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly Json $json,
        private readonly LanguageStoreMappingProviderInterface $languageMappingProvider,
        private readonly MappingVisibilityProviderInterface $visibilityProvider
    ) {
    }

    /**
     * @return array<int, array{
     *     label: string,
     *     code: string,
     *     scope: string,
     *     type: string,
     *     parameters: array<string, bool|string>,
     *     active: bool
     * }>
     */
    public function getAttributes(): array
    {
        if ($this->attributesCache !== null) {
            return $this->attributesCache;
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::ATTRIBUTE_TABLE);
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($table, ['code', 'type', 'scope', 'labels_json', 'parameters_json'])
                ->order('code ASC')
        );
        $attributes = [];
        $activeMap = $this->visibilityProvider->getActiveMap(
            'attribute',
            'ergo',
            array_map(static fn (array $row): string => (string)$row['code'], $rows)
        );

        foreach ($rows as $row) {
            $code = (string)$row['code'];
            if (in_array((string)$row['type'], self::HIDDEN_ATTRIBUTE_TYPES, true)) {
                continue;
            }

            $attributes[] = [
                'label' => $this->resolveLabel((string)$row['labels_json'], $code),
                'code' => $code,
                'scope' => (string)$row['scope'],
                'type' => (string)$row['type'],
                'parameters' => $this->decodeParameters((string)$row['parameters_json']),
                'active' => $activeMap[$code] ?? true,
            ];
        }

        return $this->attributesCache = $attributes;
    }

    /**
     * @return array<string, array{
     *     label: string,
     *     code: string,
     *     scope: string,
     *     type: string,
     *     parameters: array<string, bool|string>,
     *     active: bool
     * }>
     */
    public function getAttributeMap(): array
    {
        if ($this->attributeMapCache !== null) {
            return $this->attributeMapCache;
        }

        $attributes = [];
        foreach ($this->getAttributes() as $attribute) {
            $attributes[$attribute['code']] = $attribute;
        }

        return $this->attributeMapCache = $attributes;
    }

    /**
     * @return array{
     *     label: string,
     *     code: string,
     *     scope: string,
     *     type: string,
     *     parameters: array<string, bool|string>,
     *     active: bool
     * }|null
     */
    public function getAttribute(string $code): ?array
    {
        return $this->getAttributeMap()[$code] ?? null;
    }

    public function getRelationAttributes(): array
    {
        if ($this->relationAttributesCache !== null) {
            return $this->relationAttributesCache;
        }

        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(
                    $this->resourceConnection->getTableName(self::ATTRIBUTE_TABLE),
                    ['code', 'scope', 'labels_json']
                )
                ->where('type = ?', 'relation')
                ->order('code ASC')
        );

        return $this->relationAttributesCache = array_map(
            fn (array $row): array => [
                'label' => $this->resolveLabel((string)$row['labels_json'], (string)$row['code']),
                'code' => (string)$row['code'],
                'scope' => (string)$row['scope'],
            ],
            $rows
        );
    }

    public function reset(): void
    {
        $this->attributesCache = null;
        $this->attributeMapCache = null;
        $this->relationAttributesCache = null;
    }

    private function resolveLabel(string $labelsJson, string $fallback): string
    {
        try {
            $labels = $this->json->unserialize($labelsJson);
        } catch (InvalidArgumentException) {
            $labels = [];
        }

        if (!is_array($labels)) {
            return $fallback;
        }

        $defaultLocale = $this->languageMappingProvider->getAdminLanguageCode();

        return (string)(($defaultLocale !== null ? $labels[$defaultLocale] ?? null : null)
            ?? reset($labels)
            ?: $fallback);
    }

    /** @return array<string, bool|string> */
    private function decodeParameters(string $parametersJson): array
    {
        try {
            $parameters = $this->json->unserialize($parametersJson);
        } catch (InvalidArgumentException) {
            return [];
        }

        return is_array($parameters) ? $parameters : [];
    }
}
