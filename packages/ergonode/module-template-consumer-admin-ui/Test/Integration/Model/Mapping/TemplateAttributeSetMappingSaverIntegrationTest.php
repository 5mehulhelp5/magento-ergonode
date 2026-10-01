<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumerAdminUi\Test\Integration\Model\Mapping;

use Ergonode\Template\Api\TemplateMappingSaverInterface;
use Ergonode\TemplateConsumer\Model\Sync\AttributeSetManager;
use Ergonode\TemplateConsumer\Model\Sync\AttributeSetResource;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppArea('adminhtml'), AppIsolation(true), DbIsolation(true)]
class TemplateAttributeSetMappingSaverIntegrationTest extends TestCase
{
    private const string CREATE_ATTRIBUTE_SET_MARKER = '__create_magento_attribute_set__';
    private const string TEMPLATE_CODE = 'pending-attribute-set-template';

    #[Config('ergonode_templates/import/create_attribute_sets', '1')]
    public function testCreatesAndAssignsPendingMagentoAttributeSetIdempotently(): void
    {
        $this->seedTemplate();

        $firstSave = $this->mappingSaver()->save([
            self::TEMPLATE_CODE => self::CREATE_ATTRIBUTE_SET_MARKER,
        ]);
        $attributeSetId = $this->templateAttributeSetId();

        self::assertSame(1, $firstSave['assigned']);
        self::assertNotNull($attributeSetId);
        self::assertSame(
            $attributeSetId,
            $this->attributeSetManager()->getExistingAttributeSetIdForTemplate(self::TEMPLATE_CODE)
        );

        $secondSave = $this->mappingSaver()->save([
            self::TEMPLATE_CODE => self::CREATE_ATTRIBUTE_SET_MARKER,
        ]);

        self::assertSame(1, $secondSave['unchanged']);
        self::assertSame($attributeSetId, $this->templateAttributeSetId());
    }

    public function testSwapsAttributeSetsWithoutTemporaryUniqueConstraintConflict(): void
    {
        $firstTemplateCode = 'mapping-swap-first';
        $secondTemplateCode = 'mapping-swap-second';
        $this->seedTemplate($firstTemplateCode);
        $this->seedTemplate($secondTemplateCode);
        $firstAttributeSetId = Bootstrap::getObjectManager()
            ->get(AttributeSetResource::class)
            ->getDefaultProductAttributeSetId();
        $secondAttributeSetId = (int)$this->attributeSetManager()
            ->createOrGetForTemplate('mapping-swap-target')['attribute_set_id'];

        $this->mappingSaver()->save([
            $firstTemplateCode => $firstAttributeSetId,
            $secondTemplateCode => $secondAttributeSetId,
        ]);
        $this->mappingSaver()->save([
            $firstTemplateCode => $secondAttributeSetId,
            $secondTemplateCode => $firstAttributeSetId,
        ]);

        self::assertSame(
            [
                $firstTemplateCode => (string)$secondAttributeSetId,
                $secondTemplateCode => (string)$firstAttributeSetId,
            ],
            $this->connection()->fetchPairs(
                $this->connection()
                    ->select()
                    ->from(
                        $this->table('ergonode_template'),
                        ['code', 'attribute_set_id']
                    )
                    ->where('code IN (?)', [$firstTemplateCode, $secondTemplateCode])
                    ->order('code ASC')
            )
        );
    }

    private function seedTemplate(string $templateCode = self::TEMPLATE_CODE): void
    {
        $this->connection()->insert($this->table('ergonode_template'), [
            'code' => $templateCode,
            'attribute_set_id' => null,
            'content_hash' => hash('sha256', $templateCode),
            'raw_json' => '{}',
        ]);
    }

    private function templateAttributeSetId(): ?int
    {
        $value = $this->connection()->fetchOne(
            $this->connection()
                ->select()
                ->from($this->table('ergonode_template'), ['attribute_set_id'])
                ->where('code = ?', self::TEMPLATE_CODE)
        );

        return $value !== null && $value !== false ? (int)$value : null;
    }

    private function mappingSaver(): TemplateMappingSaverInterface
    {
        return Bootstrap::getObjectManager()->get(TemplateMappingSaverInterface::class);
    }

    private function attributeSetManager(): AttributeSetManager
    {
        return Bootstrap::getObjectManager()->get(AttributeSetManager::class);
    }

    private function connection(): AdapterInterface
    {
        return Bootstrap::getObjectManager()->get(ResourceConnection::class)->getConnection();
    }

    private function table(string $name): string
    {
        return Bootstrap::getObjectManager()->get(ResourceConnection::class)->getTableName($name);
    }
}
