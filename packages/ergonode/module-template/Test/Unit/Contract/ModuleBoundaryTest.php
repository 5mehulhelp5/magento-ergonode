<?php

declare(strict_types=1);

namespace Ergonode\Template\Test\Unit\Contract;

use Composer\InstalledVersions;
use PHPUnit\Framework\TestCase;

class ModuleBoundaryTest extends TestCase
{
    public function testTemplateConsumerUsesSharedDomainInsteadOfAttributeConsumer(): void
    {
        $composer = $this->composer('TemplateConsumer');
        $moduleConfig = $this->moduleConfig('TemplateConsumer');

        self::assertArrayHasKey('ergonode/module-template', $composer['require']);
        self::assertArrayNotHasKey('ergonode/module-attribute-consumer', $composer['require']);
        self::assertStringNotContainsString('Ergonode_AttributeConsumer', $moduleConfig);
    }

    public function testBaseTemplateDomainDoesNotOwnSectionsOrAttributes(): void
    {
        $schema = (string)file_get_contents($this->moduleRoot('Template') . '/etc/db_schema.xml');
        $whitelist = $this->schemaWhitelist('Template');
        $queries = (string)file_get_contents(
            $this->moduleRoot('TemplateConsumer') . '/Model/GraphQl/TemplateQueries.php'
        );

        self::assertStringContainsString('ergonode_template', $schema);
        self::assertStringContainsString('attribute_set_id', $schema);
        self::assertStringNotContainsString('ergonode_template_section', $schema);
        self::assertStringNotContainsString('ergonode_template_attribute', $schema);
        self::assertArrayNotHasKey('ergonode_template_section', $whitelist);
        self::assertArrayNotHasKey('ergonode_template_attribute', $whitelist);
        self::assertStringNotContainsString('sectionList', $queries);
        self::assertStringNotContainsString('attributeList', $queries);
    }

    public function testTemplateAttributeExtensionOwnsStructurePersistence(): void
    {
        if (!is_dir($this->moduleRoot('TemplateAttribute'))
            || !is_dir($this->moduleRoot('TemplateAttributeConsumer'))
        ) {
            self::markTestSkipped('Optional TemplateAttribute modules are in the backlog.');
        }

        $composer = $this->composer('TemplateAttributeConsumer');
        $schema = (string)file_get_contents($this->moduleRoot('TemplateAttribute') . '/etc/db_schema.xml');

        self::assertArrayHasKey('ergonode/module-template-consumer', $composer['require']);
        self::assertArrayHasKey('ergonode/module-template-attribute', $composer['require']);
        self::assertStringContainsString('ergonode_template_section', $schema);
        self::assertStringContainsString('ergonode_template_attribute', $schema);
    }

    public function testTemplateAttributeConsumerAdminUiDoesNotRequirePublisher(): void
    {
        if (!is_dir($this->moduleRoot('TemplateAttributeConsumerAdminUi'))) {
            self::markTestSkipped('Optional TemplateAttribute admin UI module is in the backlog.');
        }

        $composer = $this->composer('TemplateAttributeConsumerAdminUi');

        self::assertArrayNotHasKey('ergonode/module-template-publisher', $composer['require']);
        self::assertStringNotContainsString(
            'Ergonode_TemplatePublisher',
            $this->moduleConfig('TemplateAttributeConsumerAdminUi')
        );
    }

    public function testProductAndCategoryModulesDoNotRequireTemplateFeatures(): void
    {
        $modules = [
            'ProductAttributeConsumer',
            'ProductAttributeConsumerAdminUi',
            'AttributePublisher',
            'AttributePublisherAdminUi',
        ];

        foreach ($modules as $module) {
            $composer = $this->composer($module);
            $moduleConfig = $this->moduleConfig($module);

            self::assertArrayNotHasKey('ergonode/module-template-consumer', $composer['require']);
            self::assertArrayNotHasKey(
                'ergonode/module-template-attribute-consumer-admin-ui',
                $composer['require']
            );
            self::assertArrayNotHasKey('ergonode/module-template-publisher', $composer['require']);
            self::assertArrayNotHasKey(
                'ergonode/module-template-attribute-publisher-admin-ui',
                $composer['require']
            );
            self::assertStringNotContainsString('Ergonode_TemplateConsumer', $moduleConfig);
            self::assertStringNotContainsString('Ergonode_TemplatePublisher', $moduleConfig);
        }
    }

    /** @return array<string, mixed> */
    private function composer(string $module): array
    {
        return json_decode(
            (string)file_get_contents($this->moduleRoot($module) . '/composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    private function moduleConfig(string $module): string
    {
        return (string)file_get_contents($this->moduleRoot($module) . '/etc/module.xml');
    }

    /** @return array<string, mixed> */
    private function schemaWhitelist(string $module): array
    {
        return json_decode(
            (string)file_get_contents($this->moduleRoot($module) . '/etc/db_schema_whitelist.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    private function moduleRoot(string $module): string
    {
        $backendRoot = InstalledVersions::getRootPackage()['install_path'];
        $active = $backendRoot . '/app/code/Ergonode/' . $module;
        if (is_dir($active)) {
            return $active;
        }
        $packageName = strtolower((string)preg_replace('/(?<!^)[A-Z]/', '-$0', $module));
        $installed = $backendRoot . '/vendor/ergonode/module-' . $packageName;
        if (is_dir($installed)) {
            return $installed;
        }
        foreach (glob($backendRoot . '/packages/backlog/ergonode/*/etc/module.xml') ?: [] as $file) {
            $xml = simplexml_load_file($file);
            if ($xml !== false && (string)$xml->module['name'] === 'Ergonode_' . $module) {
                return dirname($file, 2);
            }
        }

        return $active;
    }
}
