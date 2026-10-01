<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumerAdminUi\Test\Integration\Model;

use Magento\Config\Model\Config\Structure;
use Magento\Framework\Config\FileResolverInterface;
use Magento\Config\Model\Config\Structure\Data;
use Magento\Config\Model\Config\Structure\Reader;
use Magento\Config\Model\Config\Structure\Element\Field;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[AppArea('adminhtml'), AppIsolation(true)]
class UnifiedCategoryConfigurationTest extends TestCase
{
    private Structure $structure;

    protected function setUp(): void
    {
        parent::setUp();
        $manager = Bootstrap::getObjectManager();
        // Admin UI modules are disabled in the integration profile. Merge the real
        // production XML explicitly through Magento's reader and structure model.
        $resolver = $this->createStub(FileResolverInterface::class);
        $resolver->method('get')->willReturn([
            'base.xml' => file_get_contents(
                $this->moduleRoot('CategoryConsumerAdminUi', 'module-category-consumer-admin-ui')
                    . '/etc/adminhtml/system.xml'
            ),
            'attributes.xml' => file_get_contents(
                $this->moduleRoot('CategoryAttributeConsumerAdminUi', 'module-category-attribute-consumer-admin-ui')
                    . '/etc/adminhtml/system.xml'
            ),
        ]);
        $reader = $manager->create(Reader::class, ['fileResolver' => $resolver]);
        $configuration = $reader->read('adminhtml');
        $data = $this->createStub(Data::class);
        $data->method('get')->willReturn($configuration['config']['system']);
        $this->structure = $manager->create(Structure::class, ['structureData' => $data]);
    }

    private function moduleRoot(string $module, string $package): string
    {
        $projectRoot = $this->projectRoot();
        foreach ([
            $projectRoot . '/app/code/Ergonode/' . $module,
            $projectRoot . '/packages/ergonode/' . $package,
            $projectRoot . '/vendor/ergonode/' . $package,
        ] as $root) {
            if (is_file($root . '/composer.json')) {
                return $root;
            }
        }

        throw new RuntimeException('Cannot locate Ergonode_' . $module . '.');
    }

    private function projectRoot(): string
    {
        $root = __DIR__;
        while (!is_file($root . '/app/etc/config.php')) {
            $parent = dirname($root);
            if ($parent === $root) {
                throw new RuntimeException('Cannot locate the Magento project root.');
            }
            $root = $parent;
        }

        return $root;
    }

    public function testMergedFieldsKeepTheirOriginalConfigurationPaths(): void
    {
        $structure = $this->structure;
        $codes = [
            'name_mode', 'is_active_mode', 'is_active_default', 'include_in_menu_mode', 'include_in_menu_default',
        ];
        foreach ($codes as $code) {
            $oldPath = 'ergonode_category_attributes/synchronization/' . $code;
            $field = $structure->getElementByConfigPath($oldPath);
            self::assertInstanceOf(Field::class, $field);
            self::assertSame('ergonode_categories/data_cron/' . $code, $field->getPath());
            self::assertSame($oldPath, $field->getConfigPath());
        }
        foreach (['status', 'schedule'] as $code) {
            $oldPath = 'ergonode_category_attributes/cron/' . $code;
            $field = $structure->getElementByConfigPath($oldPath);
            self::assertInstanceOf(Field::class, $field);
            self::assertSame('ergonode_categories/data_cron/' . $code, $field->getPath());
            self::assertSame($oldPath, $field->getConfigPath());
        }
        self::assertArrayNotHasKey(
            'label',
            $structure->getElementByConfigPath('ergonode_categories/tree/initial_page_size')->getData()
        );
        self::assertArrayNotHasKey('label', $structure->getElement('ergonode_category_attributes')->getData());
    }

    public function testDestinationAndManualDefaultsDependOnFieldsInTheirNewGroups(): void
    {
        $structure = $this->structure;
        foreach ([
            'synchronization/name_attribute' => 'ergonode_categories/synchronization/name_mode',
            'data_cron/is_active_default' => 'ergonode_categories/data_cron/is_active_mode',
            'data_cron/include_in_menu_default' => 'ergonode_categories/data_cron/include_in_menu_mode',
            'data_cron/schedule' => 'ergonode_categories/data_cron/status',
        ] as $path => $dependency) {
            $data = $structure->getElement('ergonode_categories/' . $path)->getData();
            self::assertContains($dependency, array_column($data['depends']['fields'], 'id'));
        }
    }
}
