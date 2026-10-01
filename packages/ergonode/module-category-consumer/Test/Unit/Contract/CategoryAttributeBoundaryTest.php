<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Contract;

use JsonException;
use Magento\Framework\Component\ComponentRegistrar;
use PHPUnit\Framework\TestCase;

class CategoryAttributeBoundaryTest extends TestCase
{
    /** @throws JsonException */
    public function testBaseCategoryModulesDoNotRequireCategoryAttributeModules(): void
    {
        foreach ([
            'CategoryConsumer',
            'CategoryConsumerAdminUi',
        ] as $module) {
            $moduleRoot = $this->registeredModuleRoot($module);
            $composer = $this->composer($moduleRoot . '/composer.json');
            $moduleXml = (string)file_get_contents($moduleRoot . '/etc/module.xml');

            foreach (array_keys($composer['require']) as $dependency) {
                self::assertStringNotContainsString('category-attribute', $dependency, $module);
            }
            self::assertStringNotContainsString('Ergonode_CategoryAttribute', $moduleXml, $module);
        }
    }

    /** @throws JsonException */
    public function testOptionalModulesDependOnBaseModulesInEnrichmentDirection(): void
    {
        $attributeConsumer = $this->composer(
            $this->optionalModuleRoot('CategoryAttributeConsumer', 'module-category-attribute-consumer')
                . '/composer.json'
        );
        $attributePublisher = $this->composer(
            $this->optionalModuleRoot('CategoryAttributePublisher', 'module-category-attribute-publisher')
                . '/composer.json'
        );

        self::assertArrayHasKey('ergonode/module-category-consumer', $attributeConsumer['require']);
        self::assertArrayHasKey('ergonode/module-category-publisher', $attributePublisher['require']);
    }

    public function testNeutralMappingsAndInboundSnapshotsHaveSeparateOwners(): void
    {
        $attributeConsumerSchema = (string)file_get_contents(
            $this->optionalModuleRoot('CategoryAttributeConsumer', 'module-category-attribute-consumer')
                . '/etc/db_schema.xml'
        );

        $mappingSchema = (string)file_get_contents(
            $this->optionalModuleRoot('CategoryAttribute', 'module-category-attribute') . '/etc/db_schema.xml'
        );
        self::assertStringContainsString('ergonode_category_attribute"', $attributeConsumerSchema);
        self::assertStringNotContainsString('ergonode_category_attribute_mapping', $attributeConsumerSchema);
        self::assertStringContainsString('ergonode_category_attribute_mapping', $mappingSchema);
        self::assertStringNotContainsString('ergonode_category_option_mapping', $attributeConsumerSchema);
        self::assertStringContainsString('ergonode_category_option_mapping', $mappingSchema);
        self::assertStringContainsString('ergonode_category_entity_snapshot', $attributeConsumerSchema);
        self::assertStringNotContainsString('ergonode_category_entity_snapshot', $mappingSchema);
        self::assertStringNotContainsString('name="ergonode_category_attribute"', $mappingSchema);
    }

    private function optionalModuleRoot(string $moduleDirectory, string $packageDirectory): string
    {
        $registeredRoot = (new ComponentRegistrar())->getPath(
            ComponentRegistrar::MODULE,
            'Ergonode_' . $moduleDirectory
        );
        if ($registeredRoot !== null) {
            return $registeredRoot;
        }

        $root = $this->registeredModuleRoot('CategoryConsumer');
        while (!is_file($root . '/app/etc/config.php')) {
            $parent = dirname($root);
            self::assertNotSame($root, $parent, 'Cannot locate Magento root.');
            $root = $parent;
        }

        return $root . '/packages/backlog/ergonode/' . $packageDirectory;
    }

    private function registeredModuleRoot(string $module): string
    {
        $root = (new ComponentRegistrar())->getPath(ComponentRegistrar::MODULE, 'Ergonode_' . $module);
        self::assertNotNull($root, $module);

        return $root;
    }

    /**
     * @return array{name: string, require: array<string, string>}
     * @throws JsonException
     */
    private function composer(string $path): array
    {
        /** @var array{name: string, require: array<string, string>} */
        return json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }
}
