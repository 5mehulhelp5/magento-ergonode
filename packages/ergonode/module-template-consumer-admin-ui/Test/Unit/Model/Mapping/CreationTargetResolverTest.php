<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumerAdminUi\Test\Unit\Model\Mapping;

use Ergonode\TemplateConsumer\Model\Config\TemplateConfigProvider;
use Ergonode\TemplateConsumer\Model\Sync\AttributeSetManager;
use Ergonode\TemplateConsumerAdminUi\Model\Mapping\CreationTargetResolver;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class CreationTargetResolverTest extends TestCase
{
    public function testExistingIdsDoNotTriggerCreation(): void
    {
        $config = $this->createStub(TemplateConfigProvider::class);
        $manager = $this->createMock(AttributeSetManager::class);
        $manager->expects(self::never())->method('createOrGetForTemplate');
        self::assertNull((new CreationTargetResolver($config, $manager))->resolve('shoes', 4));
    }

    public function testDisabledCreationRejectsMarkerBeforeChangingMagento(): void
    {
        $config = $this->createStub(TemplateConfigProvider::class);
        $config->method('shouldCreateAttributeSets')->willReturn(false);
        $manager = $this->createMock(AttributeSetManager::class);
        $manager->expects(self::never())->method('createOrGetForTemplate');
        $this->expectException(LocalizedException::class);
        (new CreationTargetResolver($config, $manager))->resolve('shoes', '__create_magento_attribute_set__');
    }
}
