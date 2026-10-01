<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisherAdminUi\Test\Unit\Plugin;

use Ergonode\CategoryAttributeAdminUi\Model\Mapping\AttributeMappingSaver;
use Ergonode\CategoryAttributePublisherAdminUi\Model\ErgonodeCategoryAttributeCreator;
use Ergonode\CategoryAttributePublisherAdminUi\Plugin\CategoryAttributeMappingSaverPlugin;
use Ergonode\AttributePublisher\Api\Data\AttributeStateInterface;
use PHPUnit\Framework\TestCase;

class CategoryAttributeMappingSaverPluginTest extends TestCase
{
    public function testRemoteAttributeIsReadyBeforeLocalMappingSave(): void
    {
        $calls = [];
        $state = $this->createStub(AttributeStateInterface::class);
        $creator = $this->createMock(ErgonodeCategoryAttributeCreator::class);
        $creator->expects(self::once())->method('synchronizeFromMagento')->with('category_banner', 'image')
            ->willReturnCallback(static function () use (&$calls, $state): AttributeStateInterface {
                $calls[] = 'remote';
                return $state;
            });
        $result = (new CategoryAttributeMappingSaverPlugin($creator))->beforePrepareMappings(
            $this->createStub(AttributeMappingSaver::class),
            [[
                'left' => ['code' => 'category_banner', 'type' => 'image', 'pending_create' => true],
                'right' => ['code' => 'category_banner', 'type' => 'image'],
            ]]
        );

        self::assertSame(['code' => 'category_banner'], $result[0][0]['left']);
        self::assertSame(['remote'], $calls);
    }

    public function testRejectsMissingMagentoAttributeBeforeRemoteMutation(): void
    {
        $creator = $this->createMock(ErgonodeCategoryAttributeCreator::class);
        $creator->expects(self::never())->method('synchronizeFromMagento');
        $this->expectExceptionMessage('Choose a Magento category attribute');

        (new CategoryAttributeMappingSaverPlugin($creator))->beforePrepareMappings(
            $this->createStub(AttributeMappingSaver::class),
            [[
                'left' => ['code' => 'is_active', 'type' => 'select', 'pending_create' => true],
                'right' => ['code' => '', 'type' => 'boolean'],
            ]]
        );
    }
}
