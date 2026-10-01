<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttribute\Test\Integration\Model;

use Ergonode\CategoryAttribute\Api\AttributeMappingWriterInterface;
use Ergonode\CategoryAttribute\Api\MagentoOptionProviderInterface;
use Ergonode\CategoryAttribute\Api\MappingReaderInterface;
use Ergonode\CategoryAttribute\Api\OptionMappingWriterInterface;
use Ergonode\Core\Api\MappingVisibilityProviderInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Setup\CategorySetup;
use Magento\Catalog\Setup\CategorySetupFactory;
use Magento\Eav\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class MappingPersistenceTest extends TestCase
{
    private const string OPTION_ATTRIBUTE_CODE = 'ergonode_category_mapping_it';

    public function testOptionMappingRejectsNonOptionAttributePairWithoutMutation(): void
    {
        $objects = Bootstrap::getObjectManager();
        $writer = $objects->get(AttributeMappingWriterInterface::class);
        $reader = $objects->get(MappingReaderInterface::class);
        $writer->save([[
            'left' => ['code' => 'test_title', 'type' => 'text'],
            'right' => ['code' => 'meta_title', 'type' => 'text'],
        ]], []);
        $mappingId = (int)$reader->getAttributeRows()[0]['mapping_id'];

        try {
            $objects->get(OptionMappingWriterInterface::class)->save($mappingId, [[
                'left' => ['code' => 'source_option'],
                'right' => ['code' => 'option_0'],
            ]], []);
            self::fail('A non-option attribute pair accepted an option mapping.');
        } catch (LocalizedException $exception) {
            self::assertStringContainsString('option-mappable', $exception->getMessage());
        }

        self::assertSame([], $reader->getOptionRows($mappingId));
    }

    public function testExistingMappingIdentifierAndVisibilitySurviveNeutralSave(): void
    {
        $objects = Bootstrap::getObjectManager();
        $writer = $objects->get(AttributeMappingWriterInterface::class);
        $reader = $objects->get(MappingReaderInterface::class);
        $mapping = ['left' => ['code' => 'test_title', 'type' => 'text'],
            'right' => ['code' => 'meta_title', 'type' => 'text']];
        $writer->save([$mapping], []);
        $mappingId = (int)$reader->getAttributeRows()[0]['mapping_id'];

        $stats = $writer->save([$mapping], [[
            'source' => 'magento', 'code' => 'meta_title', 'active' => false,
        ]]);

        self::assertSame(1, $stats['unchanged']);
        self::assertSame($mappingId, (int)$reader->getAttributeRows()[0]['mapping_id']);
        self::assertSame(['meta_title' => false], $objects->get(MappingVisibilityProviderInterface::class)
            ->getActiveMap('category_attribute', 'magento', ['meta_title']));
    }

    public function testValidOptionMappingSurvivesRepeatedSave(): void
    {
        $objects = Bootstrap::getObjectManager();
        $setup = $objects->get(CategorySetupFactory::class)->create([
            'setup' => $objects->get(ModuleDataSetupInterface::class),
        ]);
        $eav = $objects->get(Config::class);
        try {
            $this->createOptionAttribute($setup);
            $eav->clear();
            $options = $objects->get(MagentoOptionProviderInterface::class)
                ->getOptions(self::OPTION_ATTRIBUTE_CODE);
            self::assertCount(1, $options);

            $objects->get(AttributeMappingWriterInterface::class)->save([[
                'left' => ['code' => 'source_color', 'type' => 'select'],
                'right' => ['code' => self::OPTION_ATTRIBUTE_CODE, 'type' => 'select'],
            ]], []);
            $reader = $objects->get(MappingReaderInterface::class);
            $mappingId = (int)$reader->getAttributeRows()[0]['mapping_id'];
            $mapping = [[
                'left' => ['code' => 'source_red'],
                'right' => ['code' => $options[0]['code']],
            ]];
            $writer = $objects->get(OptionMappingWriterInterface::class);
            $writer->save($mappingId, $mapping, []);
            $optionMappingId = (int)$reader->getOptionRows($mappingId)[0]['mapping_id'];

            $stats = $writer->save($mappingId, $mapping, []);

            self::assertSame(1, $stats['unchanged']);
            self::assertSame($optionMappingId, (int)$reader->getOptionRows($mappingId)[0]['mapping_id']);
            self::assertSame([$mappingId => 1], $reader->getCompleteOptionCounts([$mappingId]));
        } finally {
            $setup->removeAttribute(Category::ENTITY, self::OPTION_ATTRIBUTE_CODE);
            $eav->clear();
        }
    }

    public function testLegacyRowRemainsReadableAndKeepsItsIdWhenUpdated(): void
    {
        $objects = Bootstrap::getObjectManager();
        $resource = $objects->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $table = $resource->getTableName('ergonode_category_attribute_mapping');
        $connection->delete($table);
        $connection->insert($table, [
            'mapping_id' => 731, 'ergonode_attribute_code' => 'existing',
            'magento_attribute_code' => 'meta_title', 'ergonode_type' => 'text',
            'magento_type' => 'text', 'status' => 'complete', 'content_hash' => 'legacy-hash', 'sort_order' => 4,
        ]);
        $reader = $objects->get(MappingReaderInterface::class);
        self::assertSame('existing', $reader->getAttributeRow(731)['ergonode_attribute_code']);
        $objects->get(AttributeMappingWriterInterface::class)->save([[
            'left' => ['code' => 'existing', 'type' => 'text'],
            'right' => ['code' => 'meta_title', 'type' => 'text'],
        ]], []);

        self::assertSame(731, (int)$reader->getAttributeRows()[0]['mapping_id']);
        self::assertSame(0, (int)$reader->getAttributeRow(731)['sort_order']);
    }

    private function createOptionAttribute(CategorySetup $setup): void
    {
        $setup->addAttribute(Category::ENTITY, self::OPTION_ATTRIBUTE_CODE, [
            'type' => 'varchar',
            'input' => 'select',
            'label' => 'Mapping integration color',
            'required' => false,
            'user_defined' => true,
        ]);
        $setup->addAttributeOption([
            'attribute_id' => (int)$setup->getAttributeId(Category::ENTITY, self::OPTION_ATTRIBUTE_CODE),
            'value' => ['red' => [0 => 'Red']],
        ]);
    }
}
