<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\Provider;

use Ergonode\AttributeConsumer\Api\ErgonodeAttributeProviderInterface;
use Ergonode\Core\Api\MappingVisibilityProviderInterface;
use Magento\Framework\App\ResourceConnection;
use Ergonode\Core\Model\Report\ChangeReport;

class ErgonodeCategoryAttributeProvider
{
    /** @var array<int, array<string, mixed>>|null */
    private ?array $attributes = null;

    public function __construct(
        private readonly ChangeReport $changeReport,
        private readonly ResourceConnection $resourceConnection,
        private readonly ErgonodeAttributeProviderInterface $attributeProvider,
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
        if ($this->attributes !== null) {
            return $this->attributes;
        }

        $codes = array_map('strval', $this->resourceConnection->getConnection()->fetchCol(
            $this->resourceConnection->getConnection()->select()
                ->from(
                    $this->resourceConnection->getTableName('ergonode_category_attribute'),
                    ['attribute_code']
                )
                ->order('attribute_code ASC')
        ));
        $allAttributes = $this->attributeProvider->getAttributeMap();
        $activeMap = $this->visibilityProvider->getActiveMap('category_attribute', 'ergo', $codes);
        $attributes = [];

        foreach ($codes as $code) {
            if (!isset($allAttributes[$code])) {
                continue;
            }
            $attribute = $allAttributes[$code];
            $attribute['active'] = $activeMap[$code] ?? true;
            $attributes[] = $attribute;
        }

        return $this->attributes = $attributes;
    }

    /** @return array<string, array<string, mixed>> */
    public function getAttributeMap(): array
    {
        $result = [];
        foreach ($this->getAttributes() as $attribute) {
            $result[(string)$attribute['code']] = $attribute;
        }

        return $result;
    }

    public function isAvailableForSynchronization(string $code): bool
    {
        if (isset($this->getAttributeMap()[$code])) {
            return true;
        }
        $this->changeReport->add(
            'category_attribute',
            $code,
            ChangeReport::ACTION_SKIPPED,
            'Category mapping skipped: Ergonode attribute is deleted or detached. Magento values preserved.'
        );

        return false;
    }

    public function reset(): void
    {
        $this->attributes = null;
    }

    /** @return array<string, mixed>|null */
    public function getAttribute(string $code): ?array
    {
        return $this->getAttributeMap()[$code] ?? null;
    }
}
