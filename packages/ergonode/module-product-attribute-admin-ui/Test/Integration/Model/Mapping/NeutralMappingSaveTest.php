<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeAdminUi\Test\Integration\Model\Mapping;

use Ergonode\ProductAttribute\Api\MappingReaderInterface;
use Ergonode\ProductAttributeAdminUi\Api\ErgonodeMetadataProviderInterface;
use Ergonode\ProductAttributeAdminUi\Api\VerifiedErgonodeMetadataRecorderInterface;
use Ergonode\ProductAttributeAdminUi\Model\Mapping\AttributeMappingSaver;
use Ergonode\ProductAttributeAdminUi\Model\Mapping\OptionMappingSaver;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class NeutralMappingSaveTest extends TestCase
{
    #[Config('ergonode_products/attributes/status', 'mapping')]
    public function testManualSaveUsesAuthoritativeMetadataAndKeepsMappingIdentityAndOptions(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $metadata = $objectManager->get(VerifiedErgonodeMetadataRecorderInterface::class);
        $metadata->recordAttribute([
            'code' => 'neutral_status_test', 'label' => 'Neutral status',
            'type' => 'select', 'scope' => 'global', 'active' => true,
        ]);
        $metadata->recordOptions('neutral_status_test', [[
            'code' => 'enabled_test', 'label' => 'Enabled',
            'type' => 'option', 'scope' => 'en_US', 'active' => true,
        ]]);
        $request = [[
            'left' => ['code' => 'neutral_status_test', 'type' => 'forged'],
            'right' => ['code' => 'status', 'type' => 'forged'],
        ]];
        self::assertArrayHasKey(
            'neutral_status_test',
            $objectManager->get(ErgonodeMetadataProviderInterface::class)->getVerifiedAttributeMap()
        );
        $attributeSaver = $objectManager->get(AttributeMappingSaver::class);
        $attributeSaver->save(
            $request,
            [[
            'source' => 'ergo', 'code' => 'neutral_status_test', 'active' => true,
            ]]
        );
        $reader = $objectManager->get(MappingReaderInterface::class);
        $rows = $reader->getAttributeRows();
        self::assertCount(1, $rows);
        $mappingId = (int)$rows[0]['mapping_id'];
        self::assertSame('select', $rows[0]['ergonode_type']);
        self::assertSame('select', $rows[0]['magento_type']);
        $optionStats = $objectManager->get(OptionMappingSaver::class)->save(
            $mappingId,
            [[
            'left' => ['code' => 'enabled_test'],
            'right' => ['code' => 'option_1'],
            ]],
            []
        );
        self::assertSame(1, $optionStats['inserted']);

        $repeat = $attributeSaver->save($request, []);
        self::assertSame(1, $repeat['unchanged']);
        self::assertSame($mappingId, (int)$reader->getAttributeRows()[0]['mapping_id']);
        self::assertCount(1, $reader->getOptionRows($mappingId));
        self::assertSame(1, (int)$reader->getOptionRows($mappingId)[0]['magento_option_id']);

        $attributeSaver->save([], []);
        self::assertSame([], $reader->getAttributeRows());
        self::assertSame([], $reader->getOptionRows($mappingId));
    }
}
