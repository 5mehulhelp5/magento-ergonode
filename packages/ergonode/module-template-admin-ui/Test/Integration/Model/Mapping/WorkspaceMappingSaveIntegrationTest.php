<?php

declare(strict_types=1);

namespace Ergonode\TemplateAdminUi\Test\Integration\Model\Mapping;

use Ergonode\Core\Api\MappingVisibilityProviderInterface;
use Ergonode\Core\Api\MappingVisibilitySaverInterface;
use Ergonode\Template\Api\ProductAttributeSetProviderInterface;
use Ergonode\Template\Api\TemplateMappingSaverInterface;
use Ergonode\TemplateAdminUi\Controller\Adminhtml\Template\SaveMapping;
use Ergonode\TemplateAdminUi\Model\Mapping\WorkspaceMappingSaver;
use Ergonode\TemplateAdminUi\Model\MappingVisibility;
use Ergonode\TemplateAdminUi\Model\TemplateUiProvider;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Response\Http;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[AppArea('adminhtml'), AppIsolation(true), DbIsolation(false)]
class WorkspaceMappingSaveIntegrationTest extends TestCase
{
    private const int ORPHANED_ATTRIBUTE_SET_ID = 65535;

    public function testFailureAfterVisibilityWriteRollsBackMappingAndVisibility(): void
    {
        $this->withTemplate(function (string $code, ResourceConnection $resource): void {
            $manager = Bootstrap::getObjectManager();
            $realVisibility = $manager->get(MappingVisibility::class);
            $failingVisibility = $this->createMock(MappingVisibility::class);
            $failingVisibility->expects(self::once())->method('save')->willReturnCallback(
                static function (array $visibility) use ($realVisibility): void {
                    $realVisibility->save($visibility);
                    throw new RuntimeException('Injected failure after visibility write.');
                }
            );

            $result = $this->executeSave($code, $resource, $failingVisibility);

            self::assertFalse($result['success']);
            self::assertNull($this->attributeSetId($resource, $code));
            self::assertTrue($this->isActive($code));
        });
    }

    public function testSuccessfulSaveCommitsMappingAndVisibility(): void
    {
        $this->withTemplate(function (string $code, ResourceConnection $resource): void {
            $result = $this->executeSave($code, $resource);

            self::assertTrue($result['success']);
            self::assertSame($this->productAttributeSetId(), $this->attributeSetId($resource, $code));
            self::assertFalse($this->isActive($code));
        });
    }

    public function testInvalidMappingDoesNotWriteVisibility(): void
    {
        $this->withTemplate(function (string $code, ResourceConnection $resource): void {
            $visibility = $this->createMock(MappingVisibility::class);
            $visibility->expects(self::never())->method('save');

            $result = $this->executeSave($code, $resource, $visibility, -1);

            self::assertFalse($result['success']);
            self::assertNull($this->attributeSetId($resource, $code));
            self::assertTrue($this->isActive($code));
        });
    }

    public function testOrphanedMappingIsExposedAsUnmappedAndCanBeReplaced(): void
    {
        $this->withTemplate(function (string $code, ResourceConnection $resource): void {
            $templates = Bootstrap::getObjectManager()->get(TemplateUiProvider::class)
                ->getTemplates();
            $template = array_values(array_filter(
                $templates,
                static fn (array $candidate): bool => $candidate['code'] === $code
            ))[0] ?? null;

            self::assertIsArray($template);
            self::assertNull($template['attribute_set_id']);
            self::assertSame('Brak attribute set', $template['status']);

            $result = $this->executeSave($code, $resource);

            self::assertTrue($result['success']);
            self::assertSame($this->productAttributeSetId(), $this->attributeSetId($resource, $code));
        }, self::ORPHANED_ATTRIBUTE_SET_ID);
    }

    /** @return array<string, mixed> */
    private function executeSave(
        string $code,
        ResourceConnection $resource,
        ?MappingVisibility $visibility = null,
        ?int $targetAttributeSetId = null
    ): array {
        $manager = Bootstrap::getObjectManager();
        $mappings = $resource->getConnection()->fetchPairs(
            $resource->getConnection()->select()
                ->from($resource->getTableName('ergonode_template'), ['code', 'attribute_set_id'])
                ->where('attribute_set_id IS NOT NULL')
        );
        $mappings[$code] = $targetAttributeSetId ?? $this->productAttributeSetId();
        $context = $manager->get(Context::class);
        $context->getRequest()->setParams([
            'mappings' => json_encode($mappings, JSON_THROW_ON_ERROR),
            'visibility' => json_encode([
                ['source' => 'ergo', 'code' => $code, 'active' => false],
            ], JSON_THROW_ON_ERROR),
        ]);
        $controller = $visibility === null
            ? $manager->create(SaveMapping::class)
            : new SaveMapping(
                $context,
                $manager->get(Json::class),
                new WorkspaceMappingSaver(
                    $resource,
                    $manager->get(TemplateMappingSaverInterface::class),
                    $visibility
                )
            );
        $response = $manager->get(Http::class);
        $controller->execute()->renderResult($response);

        return json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function withTemplate(callable $assertions, ?int $attributeSetId = null): void
    {
        $manager = Bootstrap::getObjectManager();
        $resource = $manager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $code = uniqid('audit-atomic-template-', true);
        $templateTable = $resource->getTableName('ergonode_template');
        $visibilityTable = $resource->getTableName('ergonode_mapping_visibility');

        try {
            $connection->insert($templateTable, [
                'code' => $code,
                'attribute_set_id' => $attributeSetId,
                'content_hash' => hash('sha256', $code),
                'raw_json' => '{}',
            ]);
            $manager->get(MappingVisibilitySaverInterface::class)->saveMany([[
                'entity_type' => 'template',
                'source' => 'ergo',
                'identifier' => $code,
                'active' => true,
            ]]);

            $assertions($code, $resource);
        } finally {
            $connection->delete($visibilityTable, [
                'entity_type = ?' => 'template',
                'source = ?' => 'ergo',
                'identifier = ?' => $code,
            ]);
            $connection->delete($templateTable, ['code = ?' => $code]);
        }
    }

    private function productAttributeSetId(): int
    {
        $sets = Bootstrap::getObjectManager()->get(ProductAttributeSetProviderInterface::class)
            ->getProductAttributeSets();
        self::assertNotEmpty($sets);

        return (int)$sets[0]['id'];
    }

    private function attributeSetId(ResourceConnection $resource, string $code): ?int
    {
        $value = $resource->getConnection()->fetchOne(
            $resource->getConnection()->select()
                ->from($resource->getTableName('ergonode_template'), ['attribute_set_id'])
                ->where('code = ?', $code)
        );

        return $value !== null && $value !== false ? (int)$value : null;
    }

    private function isActive(string $code): bool
    {
        $active = Bootstrap::getObjectManager()->get(MappingVisibilityProviderInterface::class)
            ->getActiveMap('template', 'ergo', [$code]);

        return $active[$code];
    }
}
